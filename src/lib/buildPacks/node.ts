import { buildImage } from '$lib/docker';
import { promises as fs } from 'fs';

const createDockerfile = async ({ image, workdir, port, installCommand, buildCommand, startCommand, baseDirectory }): Promise<void> => {
    const Dockerfile: Array<string> = []
    Dockerfile.push(`FROM ${image}`)
    Dockerfile.push('WORKDIR /usr/src/app')
    Dockerfile.push(`COPY ./${baseDirectory || ""}package*.json ./`)
    Dockerfile.push(`RUN ${installCommand}`)
    Dockerfile.push(`COPY ./${baseDirectory || ""} ./`)
    if (buildCommand) { Dockerfile.push(`RUN ${buildCommand}`) }
    Dockerfile.push(`EXPOSE ${port}`)
    Dockerfile.push(`CMD ${startCommand}`)
    await fs.writeFile(`${workdir}/Dockerfile`, Dockerfile.join('\n'))
}

export default async function ({ applicationId, commit, workdir, docker, buildId, port, installCommand, buildCommand, startCommand, baseDirectory }) {
    // TODO: Select node version
    const image = 'node:lts'
    await createDockerfile({ image, workdir, port, installCommand, buildCommand, startCommand, baseDirectory })
    await buildImage({ applicationId, commit, workdir, docker, buildId })
}