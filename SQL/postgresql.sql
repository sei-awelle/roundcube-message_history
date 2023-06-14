CREATE TABLE IF NOT EXISTS "message_history_v2" (
	"message_id" SERIAL PRIMARY KEY,
	"from_user_name" varchar(255) NOT NULL,
	"to_user_name" varchar(255) NOT NULL,
	"subject" varchar(255) NOT NULL,
	"time_sent" timestamptz NOT NULL,
	"modified" timestamptz NOT NULL,
	"read_status" BOOLEAN DEFAULT false,
	"roundcube_message_id" varchar(255),
	"exercise" varchar(255)
);

