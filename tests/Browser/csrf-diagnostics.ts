import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import type { Page, Request, Response } from '@playwright/test';

function requestCookies(headers: Record<string, string>): { values: Map<string, string>; duplicates: string[] } {
    const cookies = (headers.cookie ?? '').split(';').flatMap((cookie): [string, string][] => {
        const separator = cookie.indexOf('=');
        if (separator < 0) {
            return cookie.trim() ? [[cookie.trim(), '']] : [];
        }

        return [[cookie.slice(0, separator).trim(), cookie.slice(separator + 1).trim()]];
    });
    const values = new Map<string, string>();
    const names = new Set<string>();
    const duplicates = new Set<string>();

    for (const [name, value] of cookies) {
        if (names.has(name)) duplicates.add(name);
        names.add(name);
        values.set(name, value);
    }

    return { values, duplicates: [...duplicates].sort() };
}

export async function waitForLoginResponse(page: Page): Promise<Response> {
    const responsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/login',
    );

    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    const response = await responsePromise;

    const request = response.request();
    const headers = await request.allHeaders();
    const { values: cookieValues, duplicates } = requestCookies(headers);
    const sessionCookieName = process.env.SESSION_COOKIE
        ?? (process.env.FREECAST_PUBLIC_PILOT ? 'freecast_pilot_session' : 'freecast-session');
    const xsrfCookie = cookieValues.get('XSRF-TOKEN');
    const xsrfHeader = headers['x-xsrf-token'];
    const sessionCookie = (await page.context().cookies(response.url()))
        .find((cookie) => cookie.name === sessionCookieName);
    let xsrfHeaderMatchesCookie = false;
    if (typeof xsrfCookie === 'string' && typeof xsrfHeader === 'string') {
        try {
            xsrfHeaderMatchesCookie = decodeURIComponent(xsrfCookie) === xsrfHeader;
        } catch (error) {
            if (!(error instanceof URIError)) {
                throw error;
            }
        }
    }
    const diagnostic = {
        method: request.method(),
        origin: new URL(response.url()).origin,
        status: response.status(),
        cookieNames: [...cookieValues.keys()].filter(Boolean).sort(),
        duplicateCookieNames: duplicates,
        hasSessionCookie: cookieValues.has(sessionCookieName),
        sessionCookieSecure: sessionCookie?.secure ?? false,
        sessionCookieHostOnly: sessionCookie ? !sessionCookie.domain.startsWith('.') : false,
        sessionCookieSameSite: sessionCookie?.sameSite ?? null,
        hasXsrfCookie: typeof xsrfCookie === 'string' && xsrfCookie.length > 0,
        hasXsrfHeader: typeof xsrfHeader === 'string' && xsrfHeader.length > 0,
        xsrfHeaderMatchesCookie,
        contentType: headers['content-type'] ?? null,
    };

    if (response.status() === 419) {
        const filePath = resolve(
            process.env.FREECAST_DIAGNOSTIC_DIR ?? 'test-results',
            `csrf-419-${Date.now()}.json`,
        );
        await mkdir(dirname(filePath), { recursive: true });
        await writeFile(filePath, `${JSON.stringify(diagnostic, null, 2)}\n`, 'utf8');

        throw new Error(`Login returned HTTP 419. Cookie/token metadata saved to ${filePath} without their values.`);
    }

    if (process.env.FREECAST_PUBLIC_PILOT) {
        const expectedHost = new URL(process.env.FREECAST_BASE_URL ?? '').hostname;
        const invalidMetadata = !diagnostic.hasSessionCookie
            || duplicates.includes(sessionCookieName)
            || !diagnostic.sessionCookieSecure
            || !diagnostic.sessionCookieHostOnly
            || diagnostic.sessionCookieSameSite !== 'Lax'
            || !diagnostic.hasXsrfCookie
            || !diagnostic.hasXsrfHeader
            || !diagnostic.xsrfHeaderMatchesCookie
            || sessionCookie?.domain.replace(/^\./, '') !== expectedHost;

        if (invalidMetadata) {
            throw new Error(`Pilot login cookie/CSRF metadata is inconsistent: ${JSON.stringify(diagnostic)}.`);
        }
    }

    return response;
}
