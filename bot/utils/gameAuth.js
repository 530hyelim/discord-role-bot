import jwt from 'jsonwebtoken';

const SECRET = process.env.GAME_JWT_SECRET || process.env.TOKEN || 'game-secret-change-in-production';
const EXPIRES_IN = '2h';

export function createGameToken(payload) {
    return jwt.sign(
        { guild_id: payload.guild_id, user_id: payload.user_id, username: payload.username },
        SECRET,
        { expiresIn: EXPIRES_IN }
    );
}

export function verifyGameToken(token) {
    try {
        return jwt.verify(token, SECRET);
    } catch {
        return null;
    }
}
