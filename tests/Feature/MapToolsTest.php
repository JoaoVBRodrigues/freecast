<?php

namespace Tests\Feature;

use App\Models\{Room, RoomAsset, RoomCharacter, RoomCombat, RoomMapDrawing, RoomMapObject, User};
use App\Services\{MapVisibility, RoomCharacterData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Event, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class MapToolsTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $owner = User::factory()->create(); $player = User::factory()->create(); $third = User::factory()->create();
        $room = Room::create(['owner_id' => $owner->id, 'title' => 'Ferramentas', 'system' => 'Próprio']);
        foreach ([[$owner,'owner'],[$player,'player'],[$third,'player']] as [$u,$r]) $room->memberships()->create(['user_id'=>$u->id,'role'=>$r]);
        $map = $room->maps()->create(['created_by'=>$owner->id,'title'=>'Campo','width'=>800,'height'=>600,'cell_size'=>50]);
        return [$owner,$player,$third,$room,$map];
    }

    private function command($user, $room, $map, array $data)
    {
        return $this->actingAs($user)->postJson(route('maps.tools.command',[$room,$map]), [
            'idempotency_key'=>(string) Str::uuid(), 'expected_revision'=>$map->fresh()->revision, ...$data,
        ]);
    }

    public function test_drawings_limits_ownership_revision_replay_and_room_boundary(): void
    {
        [$owner,$player,$third,$room,$map] = $this->fixtures();
        $data = ['action'=>'draw','kind'=>'line','color'=>'#eb3449','stroke_width'=>4,'points'=>[['x'=>70,'y'=>70],['x'=>120,'y'=>120]]];
        foreach (['line','rectangle','ellipse','freehand'] as $kind) $this->command($player,$room,$map,[...$data,'kind'=>$kind])->assertOk();
        $this->assertDatabaseCount('room_map_drawings',4);
        $drawing=RoomMapDrawing::first();
        $this->command($third,$room,$map,['action'=>'delete','drawing_id'=>$drawing->id,'drawing_revision'=>1])->assertForbidden();
        $key=(string) Str::uuid(); $revision=$map->fresh()->revision;
        $delete=['action'=>'delete','drawing_id'=>$drawing->id,'drawing_revision'=>1,'idempotency_key'=>$key,'expected_revision'=>$revision];
        $this->command($player,$room,$map,$delete)->assertOk();
        $this->command($player,$room,$map,$delete)->assertOk()->assertJsonPath('replayed',true);
        $this->command($player,$room,$map,[...$delete,'drawing_revision'=>2])->assertConflict();
        $this->command($player,$room,$map,[...$data,'expected_revision'=>1])->assertConflict();
        $this->command($owner,$room,$map,['action'=>'delete','drawing_id'=>RoomMapDrawing::first()->id,'drawing_revision'=>1])->assertOk();
        foreach ([['kind'=>'<svg>'],['color'=>'url(script)'],['stroke_width'=>99],['points'=>array_fill(0,257,['x'=>1,'y'=>1])],['points'=>[['x'=>900,'y'=>0],['x'=>0,'y'=>1]]]] as $bad) $this->command($player,$room,$map,[...$data,...$bad])->assertUnprocessable();
        $this->actingAs($owner)->postJson(route('maps.tools.command',[$room,$map]),[...$data,'junk'=>str_repeat('x',33000)])->assertStatus(413);
        $other=Room::create(['owner_id'=>$third->id,'title'=>'Outra','system'=>'Próprio']);
        $other->memberships()->create(['user_id'=>$third->id,'role'=>'owner']);
        $this->command($third,$other,$map,$data)->assertNotFound();
        $events=$this->actingAs($third)->getJson(route('rooms.events',$room))->assertOk()->json('events');
        foreach ($events as $event) {
            $this->assertSame('map.contents.changed',$event['type']); $this->assertNull($event['entityId']);
            $this->assertSame(['map_id'=>$map->id],$event['payload']);
        }
    }

    public function test_fog_hides_tokens_sheets_combat_portraits_previews_and_conflict_payloads(): void
    {
        [$owner,$player,$third,$room,$map]=$this->fixtures();
        Storage::disk('local')->put('fog-portrait.png','synthetic');
        $asset=RoomAsset::create(['room_id'=>$room->id,'uploaded_by'=>$owner->id,'kind'=>'token','name'=>'Template público','path'=>'fog-portrait.png','mime_type'=>'image/png','size'=>9,'width'=>50,'height'=>50]);
        $layer=$map->layers()->create(['type'=>'tokens','name'=>'Tokens','sort_order'=>2]);
        $character=RoomCharacter::create(['room_id'=>$room->id,'name'=>'SEGREDO-NEVOA','is_public'=>true,'assigned_user_id'=>$player->id,'portrait_asset_id'=>$asset->id,'hp_max'=>30,'hp_current'=>17]);
        $token=$layer->objects()->create(['room_map_id'=>$map->id,'room_asset_id'=>$asset->id,'character_id'=>$character->id,'assigned_user_id'=>$player->id,'name'=>'INSTANCIA-COBERTA','x'=>64,'y'=>64,'width'=>50,'height'=>50]);
        RoomCombat::create(['room_id'=>$room->id,'active'=>true,'active_id'=>'actor','round'=>1,'participants'=>[['id'=>'actor','character_id'=>$character->id,'token_id'=>$token->id,'initiative'=>12,'order'=>1]]]);
        $this->command($player,$room,$map,['action'=>'fog','mode'=>'enable'])->assertForbidden();
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'enable'])->assertOk();
        foreach ([$player,$third] as $viewer) {
            $this->actingAs($viewer)->get(route('maps.show',[$room,$map]))->assertDontSee('SEGREDO-NEVOA')->assertDontSee('INSTANCIA-COBERTA');
            $this->actingAs($viewer)->getJson(route('characters.index',$room))->assertJsonCount(0,'characters')->assertJsonCount(0,'combat.participants')->assertJsonPath('combat.active_id',null);
            $this->actingAs($viewer)->get(route('characters.portrait',[$room,$character]))->assertNotFound();
        }
        $this->actingAs($player)->postJson(route('characters.command',$room),['action'=>'update','character_id'=>$character->id,'expected_revision'=>1,'hp_current'=>1,'idempotency_key'=>(string) Str::uuid()])->assertForbidden();
        $move=['x'=>128,'y'=>128,'expected_revision'=>99,'idempotency_key'=>(string) Str::uuid()];
        $this->actingAs($player)->patchJson(route('maps.objects.update',[$room,$map,$token]),$move)->assertForbidden()->assertDontSee('SEGREDO-NEVOA');
        $this->actingAs($player)->postJson(route('maps.objects.preview',[$room,$map,$token]),['x'=>64,'y'=>64,'revision'=>1])->assertForbidden();
        Event::fake([\App\Events\RoomObjectPreview::class]);
        $this->actingAs($owner)->postJson(route('maps.objects.preview',[$room,$map,$token]),['x'=>64,'y'=>64,'revision'=>1])->assertOk();
        Event::assertDispatched(\App\Events\RoomObjectPreview::class,fn($e)=>$e->audience==='masters');
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'reveal','rect'=>['x'=>64,'y'=>64,'width'=>64,'height'=>64]])->assertOk();
        $this->actingAs($player)->getJson(route('characters.index',$room))->assertJsonPath('characters.0.name','SEGREDO-NEVOA')->assertJsonCount(1,'combat.participants');
        $this->actingAs($player)->patchJson(route('maps.objects.update',[$room,$map,$token]),[...$move,'expected_revision'=>1])->assertForbidden(); // destination covered
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'cover','rect'=>['x'=>64,'y'=>64,'width'=>64,'height'=>64]])->assertOk();
        $this->assertFalse(app(RoomCharacterData::class)->visible($room,$player,$character));
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'reveal_all'])->assertUnprocessable();
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'reveal_all','confirmed'=>true])->assertOk();
        $this->assertTrue(app(MapVisibility::class)->point($map->fresh(),799,599));
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'cover_all','confirmed'=>true])->assertOk();
        $this->assertSame([],$map->fresh()->fog_revealed);
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'reveal','rect'=>['x'=>799,'y'=>0,'width'=>2,'height'=>1]])->assertUnprocessable();
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'disable'])->assertOk();
        $this->assertTrue(app(RoomCharacterData::class)->visible($room,$player,$character));
    }

    public function test_conservative_drawing_mask_scale_bounds_and_legacy_events(): void
    {
        [$owner,$player,$third,$room,$map]=$this->fixtures();
        $this->command($owner,$room,$map,['action'=>'scale','distance_scale'=>2.5,'distance_unit'=>'metros'])->assertOk();
        $this->assertEquals(2.5,$map->fresh()->distance_scale);
        $this->command($player,$room,$map,['action'=>'scale','distance_scale'=>1,'distance_unit'=>'m'])->assertForbidden();
        $this->command($owner,$room,$map,['action'=>'scale','distance_scale'=>0,'distance_unit'=>'m'])->assertUnprocessable();
        $draw=['action'=>'draw','kind'=>'line','color'=>'#ffffff','stroke_width'=>2,'points'=>[['x'=>70,'y'=>70],['x'=>190,'y'=>70]]];
        $this->command($player,$room,$map,$draw)->assertOk();
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'enable'])->assertOk();
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'reveal','rect'=>['x'=>64,'y'=>64,'width'=>64,'height'=>64]])->assertOk();
        $this->actingAs($player)->get(route('maps.show',[$room,$map]))->assertInertia(fn($p)=>$p->has('drawings',0));
        $this->command($player,$room,$map,$draw)->assertForbidden();
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'reveal','rect'=>['x'=>128,'y'=>64,'width'=>64,'height'=>64]])->assertOk();
        $this->actingAs($player)->get(route('maps.show',[$room,$map]))->assertInertia(fn($p)=>$p->has('drawings',1));
        $legacy=['schemaVersion'=>1,'eventId'=>(string)Str::uuid(),'stream'=>'room','roomId'=>$room->id,'sequence'=>100,'type'=>'map.object.updated','entityId'=>'SECRET-ID','entityRevision'=>7,'occurredAt'=>now()->toIso8601String(),'payload'=>['map_id'=>$map->id,'object_id'=>'SECRET-ID']];
        $room->events()->create(['id'=>$legacy['eventId'],'audience'=>'room','sequence'=>100,'event_type'=>'map.object.updated','entity_id'=>'SECRET-ID','payload'=>$legacy,'command_id'=>(string)Str::uuid(),'occurred_at'=>now()]);
        $this->actingAs($player)->getJson(route('rooms.events',$room))->assertDontSee('SECRET-ID');
        $broadcast=(new \App\Events\RoomStateChanged('room',$legacy))->broadcastWith();
        $this->assertSame('map.contents.changed',$broadcast['type']); $this->assertNull($broadcast['entityId']);
    }

    public function test_duplicate_preserves_tools_and_resize_resets_mask_without_reinterpreting_indices(): void
    {
        [$owner,$player,$third,$room,$map]=$this->fixtures();
        $this->command($owner,$room,$map,['action'=>'draw','kind'=>'rectangle','color'=>'#ffd166','stroke_width'=>4,'points'=>[['x'=>70,'y'=>70],['x'=>120,'y'=>120]]])->assertOk();
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'reveal','rect'=>['x'=>64,'y'=>64,'width'=>64,'height'=>64]])->assertOk();
        $this->command($owner,$room,$map,['action'=>'fog','mode'=>'enable'])->assertOk();
        $this->command($owner,$room,$map,['action'=>'scale','distance_scale'=>3,'distance_unit'=>'km'])->assertOk();
        $this->actingAs($owner)->post(route('maps.duplicate',[$room,$map]),['idempotency_key'=>(string)Str::uuid()])->assertRedirect();
        $copy=$room->maps()->where('id','!=',$map->id)->firstOrFail();
        $this->assertTrue($copy->fog_enabled);
        $this->assertSame($map->fresh()->fog_revealed,$copy->fog_revealed);
        $this->assertEquals(3,$copy->distance_scale); $this->assertSame('km',$copy->distance_unit);
        $this->assertSame(RoomMapDrawing::where('room_map_id',$map->id)->first()->points,RoomMapDrawing::where('room_map_id',$copy->id)->first()->points);
        $this->actingAs($owner)->patch(route('maps.update',[$room,$map]),['width'=>900,'expected_revision'=>$map->fresh()->revision,'idempotency_key'=>(string)Str::uuid()])->assertRedirect();
        $this->assertSame([],$map->fresh()->fog_revealed);
        $this->assertTrue($map->fresh()->fog_enabled);
    }
}
