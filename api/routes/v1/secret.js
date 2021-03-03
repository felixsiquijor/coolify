const Secret = require("../../models/Secret");
const { encryptData } = require("../../libs/common");

module.exports = async function (fastify) {
  const getSecret = {
    querystring: {
      type: "object",
      properties: {
        repoId: { type: "number" },
        branch: { type: "string" },
      },
      required: ["repoId", "branch"],
    },
  };
  const saveSecret = {
    body: {
      type: "object",
      properties: {
        repoId: { type: "number" },
        branch: { type: "string" },
        name: { type: "string" },
        value: { type: "string" },
      },
      required: ["repoId", "branch", "name", "value"],
    },
  };
  const deleteSecret = {
    body: {
      type: "object",
      properties: {
        repoId: { type: "number" },
        branch: { type: "string" },
        name: { type: "string" }
      },
      required: ["repoId", "branch", "name"],
    },
  };
  fastify.get("/", { schema: getSecret }, async (request, reply) => {
    const { repoId, branch } = request.query;
    return await Secret.find({ repoId, branch }).select("-_id -__v -value").sort('name');
  });

  fastify.post("/", { schema: saveSecret }, async (request, reply) => {
    const { repoId, branch, name, value } = request.body;
    delete request.body.createdAt;
    delete request.body.updatedAt;
    try {
      await Secret.findOneAndUpdate(
        { repoId, branch, name },
        { name, value: encryptData(value) },
        { upsert: true, new: true }
      ).select("-_id -__v");
      reply.code(201).send({});
    } catch (error) {
      throw new Error(error);
    }
  });
  fastify.delete("/", { schema: deleteSecret }, async (request, reply) => {
    const { repoId, branch, name } = request.body;
    return await Secret.findOneAndDelete({ repoId, branch, name }).select("-_id -__v -value");
  });
};
