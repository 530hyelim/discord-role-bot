import { supabase } from './index.js';

export async function upsertUserScore(userId, username, score) {
    const { data: userRows, error: selectError } = await supabase
        .from('users')
        .select('total_score')
        .eq('user_id', userId)
        .single();

    if (selectError && selectError.code !== 'PGRST116') throw selectError; // PGRST116: row not found

    if (userRows && userRows.total_score !== undefined) {
        score += userRows.total_score;
    }

    const { error: upsertError } = await supabase
        .from('users')
        .upsert(
            { user_id: userId, username, total_score: score },
            { onConflict: ['user_id'] }
        );

    if (upsertError) throw new Error('점수 업데이트 실패..');
}
