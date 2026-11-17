-- 1.21.0: per-user cache in the database (was the session).
CREATE TABLE IF NOT EXISTS "#__sdp_cache" (
  "user_id" bigint NOT NULL,
  "cache_key" varchar(191) NOT NULL,
  "value" text NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("user_id", "cache_key")
);
CREATE INDEX IF NOT EXISTS "#__sdp_cache_idx_created" ON "#__sdp_cache" ("created");
