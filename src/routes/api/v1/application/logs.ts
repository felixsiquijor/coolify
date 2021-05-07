import { saveServerLog } from '$lib/api/applications/logging';
import { docker } from '$lib/docker';
import type { Request } from '@sveltejs/kit';

export async function get(request: Request) {
    try {
        const name = request.query.get('name')
        const service = await docker.engine.getService(`${name}_${name}`)
        const logs = (await service.logs({ stdout: true, stderr: true, timestamps: true })).toString().split('\n').map(l => l.slice(8)).filter((a) => a)
        return {
            status: 200,
            body: { logs }
        }
    } catch (error) {
        await saveServerLog(error)
        return {
            status: 500,
            body: { 
                error
             }
        }
    }
}
