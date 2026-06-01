/** Tiny helpers for same-origin JSON POSTs that satisfy Laravel's CSRF guard. */

export function xsrfToken(): string {
    const match = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export async function postJson<T = unknown>(
    url: string,
    body: unknown,
): Promise<{ ok: boolean; status: number; data: T }> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    let data = null as unknown as T;
    try {
        data = (await res.json()) as T;
    } catch {
        /* non-JSON response */
    }
    return { ok: res.ok, status: res.status, data };
}
