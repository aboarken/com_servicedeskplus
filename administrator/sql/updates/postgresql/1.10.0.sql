-- 1.10.0: "My past decisions" shows what each decision was about.
ALTER TABLE "#__sdp_audit" ADD COLUMN "module" varchar(20) DEFAULT '' NOT NULL;
ALTER TABLE "#__sdp_audit" ADD COLUMN "subject" varchar(255) DEFAULT '' NOT NULL;
