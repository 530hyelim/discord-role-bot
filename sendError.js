import 'dotenv/config';
import { client } from './index.js';

export async function sendError(content) {
    const logChannel = client.channels.cache.get(process.env.LOG_CHANNEL_ID);

    if (logChannel) {
        await logChannel.send(content);
    } else {
        let admins = [];
        const guild = client.guilds.cache.get(process.env.GUILD_ID);

        if (guild) {
            admins = message.guild.members.cache.filter(member => member.permissions.has(process.env.ROLE_ADMIN_ID));

            if (!admins || admins.size === 0) {
                const members = await message.guild.members.fetch();
                admins = members.filter(member => member.permissions.has(process.env.ROLE_ADMIN_ID));
            }
        }

        for (const [id, member] of admins) {
            await member.send(content);
        }
    }
}