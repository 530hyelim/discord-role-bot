import { SlashCommandBuilder } from 'discord.js';
import { createGameToken } from '../utils/gameAuth.js';

const BASE_URL = process.env.BASE_URL || process.env.RENDER_EXTERNAL_URL || 'http://localhost:3000';

export default {
    data: new SlashCommandBuilder()
        .setName('game')
        .setDescription('포인트 게임'),

    async execute(interaction) {
        try {
            const guildId = interaction.guild?.id;
            const userId = interaction.user?.id;
            const username = interaction.member?.displayName || interaction.user?.username;

            if (!guildId || !userId) {
                return interaction.reply({ content: '서버 안에서만 사용할 수 있어요.', flags: 64 });
            }

            const token = createGameToken({ guild_id: guildId, user_id: userId, username });
            const url = `${BASE_URL.replace(/\/$/, '')}/game?token=${encodeURIComponent(token)}`;

            await interaction.reply({
                content: `🎮 **포인트 연동 게임**\n\n${url}\n\n※ 링크는 2시간 동안 유효`,
                flags: 64,
            });
        } catch (err) {
            console.error('game command error:', err);
            if (!interaction.replied && !interaction.deferred) {
                await interaction.reply({ content: '링크 생성 중 오류 발생', flags: 64 }).catch(() => {});
            }
        }
    },
};
