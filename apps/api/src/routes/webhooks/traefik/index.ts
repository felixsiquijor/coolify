import { FastifyPluginAsync } from 'fastify';
import {  traefikConfiguration } from './handlers';

const root: FastifyPluginAsync = async (fastify, opts): Promise<void> => {
    fastify.get('/main.json', async (request, reply) => traefikConfiguration(request, reply));
};

export default root;
