-- 1.1.0: approvals use the service key; per-user approver keys are no longer stored.
-- Remove the table so no user keys remain in the database.
DROP TABLE IF EXISTS `#__sdp_user_keys`;
