use sqlx::PgPool;

const LOCK_ISS: i64 = 1001;
const LOCK_OSDR: i64 = 1002;
const LOCK_APOD: i64 = 1003;
const LOCK_NEO: i64 = 1004;
const LOCK_DONKI: i64 = 1005;
const LOCK_SPACEX: i64 = 1006;

pub fn lock_id(name: &str) -> i64 {
    match name {
        "iss" => LOCK_ISS,
        "osdr" => LOCK_OSDR,
        "apod" => LOCK_APOD,
        "neo" => LOCK_NEO,
        "donki" => LOCK_DONKI,
        "spacex" => LOCK_SPACEX,
        _ => 0,
    }
}

pub async fn try_advisory_lock(pool: &PgPool, lock_id: i64) -> bool {
    sqlx::query_scalar::<_, bool>("SELECT pg_try_advisory_lock($1)")
        .bind(lock_id)
        .fetch_one(pool)
        .await
        .unwrap_or(false)
}

pub async fn release_advisory_lock(pool: &PgPool, lock_id: i64) {
    let _ = sqlx::query("SELECT pg_advisory_unlock($1)")
        .bind(lock_id)
        .execute(pool)
        .await;
}
