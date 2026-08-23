import mysql from 'mysql2/promise';

export const pool = mysql.createPool({
    host: process.env.DB_HOST,
    port: process.env.DB_PORT || 3306,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    waitForConnections: true,
    connectionLimit: 5,
    queueLimit: 0,
    timezone: '+09:00',
});

/** 단일 행 조회, 없으면 null */
export async function queryOne(sql, params = []) {
    const [rows] = await pool.query(sql, params);
    return rows[0] ?? null;
}

/** 여러 행 조회 */
export async function queryAll(sql, params = []) {
    const [rows] = await pool.query(sql, params);
    return rows;
}

/** INSERT/UPDATE/DELETE 실행, 결과 메타(affectedRows, insertId 등) 반환 */
export async function execute(sql, params = []) {
    const [result] = await pool.query(sql, params);
    return result;
}
