CREATE TABLE IF NOT EXISTS "tagconfig" ("tag_id" VARCHAR(255), "tag_uri" VARCHAR(255), "tag_desc" VARCHAR(255));
CREATE UNIQUE INDEX "rfidplayer_tag_id" ON "tagconfig" ("tag_id");
CREATE TABLE IF NOT EXISTS 'tagstats' ('tag_id' TEXT, 'timestamp'  DATETIME DEFAULT CURRENT_TIMESTAMP  );
