-- 1.10.0: "My past decisions" shows what each decision was about.
ALTER TABLE `#__sdp_audit` ADD COLUMN `module` VARCHAR(20) NOT NULL DEFAULT '' AFTER `approval_id`;
ALTER TABLE `#__sdp_audit` ADD COLUMN `subject` VARCHAR(255) NOT NULL DEFAULT '' AFTER `module`;
