CREATE TABLE IF NOT EXISTS "message_history" (
	"message_id" SERIAL PRIMARY KEY,
        "from_user_id" int NOT NULL,
	"to_user_id" int NOT NULL,
	"subject" varchar(255) NOT NULL,
	"time_sent" timestamptz NOT NULL,
	"modified" timestamptz NOT NULL
);


