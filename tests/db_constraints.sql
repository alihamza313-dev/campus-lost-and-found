-- Run with:  mysql --force -u clf_user -p clf_db < tests/db_constraints.sql
-- --force keeps going after an error. 13 errors are EXPECTED (listed below).

START TRANSACTION;

-- Setup: 3 users, 1 found item, 1 claim --------------------------------
INSERT INTO users (full_name, university_email, password_hash, role, verification_status) VALUES
    ('Test Poster',    'poster@pucit.edu.pk',    'x', 'student',   'verified'),
    ('Test Claimant',  'claimant@pucit.edu.pk',  'x', 'student',   'verified'),
    ('Test Custodian', 'custodian@pucit.edu.pk', 'x', 'custodian', 'verified');
SET @poster  = (SELECT user_id FROM users WHERE university_email='poster@pucit.edu.pk');
SET @claimer = (SELECT user_id FROM users WHERE university_email='claimant@pucit.edu.pk');
SET @cust    = (SELECT user_id FROM users WHERE university_email='custodian@pucit.edu.pk');

INSERT INTO items (reporter_id, report_type, title, category_id, location_id, description, incident_date)
VALUES (@poster, 'found', 'Black wallet',
        (SELECT MIN(category_id) FROM categories), (SELECT MIN(location_id) FROM campus_locations),
        'Found near library', CURDATE());
SET @item = LAST_INSERT_ID();

INSERT INTO claims (item_id, claimant_id, ownership_details) VALUES (@item, @claimer, 'It is mine');
SET @claim = LAST_INSERT_ID();

-- E1 ---------------------------------------------------------------
SELECT 'E1 duplicate email (EXPECT ERROR 1062)' AS test;
INSERT INTO users (full_name, university_email, password_hash) VALUES ('Dup', 'POSTER@pucit.edu.pk', 'x');

-- E2 ---------------------------------------------------------------
SELECT 'E2 invalid role (EXPECT ERROR 1265)' AS test;
INSERT INTO users (full_name, university_email, password_hash, role) VALUES ('Bad', 'bad@pucit.edu.pk', 'x', 'hacker');

-- E3 ---------------------------------------------------------------
SELECT 'E3 same user claims same item twice (EXPECT ERROR 1062) [T-06]' AS test;
INSERT INTO claims (item_id, claimant_id, ownership_details) VALUES (@item, @claimer, 'again');

-- E4 ---------------------------------------------------------------
SELECT 'E4 reviewer is the claimant (EXPECT CHECK ERROR 3819) [T-09]' AS test;
UPDATE claims SET reviewed_by = claimant_id WHERE claim_id = @claim;

-- E5 ---------------------------------------------------------------
SELECT 'E5 item_detail evidence with a claim_id (EXPECT CHECK ERROR 3819)' AS test;
INSERT INTO private_item_evidence (item_id, claim_id, evidence_type, submitted_by, private_description)
VALUES (@item, @claim, 'item_detail', @poster, 'serial 123');

-- E6 ---------------------------------------------------------------
SELECT 'E6 claim_proof evidence without a claim_id (EXPECT CHECK ERROR 3819)' AS test;
INSERT INTO private_item_evidence (item_id, claim_id, evidence_type, submitted_by, private_description)
VALUES (@item, NULL, 'claim_proof', @claimer, 'receipt');

-- E7 ---------------------------------------------------------------
SELECT 'E7 completed handover with no completed_at (EXPECT CHECK ERROR 3819)' AS test;
INSERT INTO handovers (claim_id, custodian_id, handover_status, scheduled_at)
VALUES (@claim, @cust, 'completed', NOW());

-- Valid handover (must succeed) ------------------------------------
INSERT INTO handovers (claim_id, custodian_id, scheduled_at) VALUES (@claim, @cust, NOW());

-- E8 ---------------------------------------------------------------
SELECT 'E8 second handover for same claim (EXPECT ERROR 1062)' AS test;
INSERT INTO handovers (claim_id, custodian_id, scheduled_at) VALUES (@claim, @cust, NOW());

-- E9 ---------------------------------------------------------------
SELECT 'E9 message to yourself (EXPECT CHECK ERROR 3819)' AS test;
INSERT INTO messages (item_id, sender_id, recipient_id, message_text) VALUES (@item, @poster, @poster, 'hi me');

-- E10 --------------------------------------------------------------
SELECT 'E10 report with no target (EXPECT CHECK ERROR 3819)' AS test;
INSERT INTO reports (reporter_id, reason) VALUES (@poster, 'spam');

-- E11 --------------------------------------------------------------
SELECT 'E11 item with a category that does not exist (EXPECT FK ERROR 1452)' AS test;
INSERT INTO items (reporter_id, report_type, title, category_id, location_id, description, incident_date)
VALUES (@poster, 'lost', 'Ghost', 999999, (SELECT MIN(location_id) FROM campus_locations), 'x', CURDATE());

-- E12 --------------------------------------------------------------
SELECT 'E12 delete a user who owns an item (EXPECT FK ERROR 1451)' AS test;
DELETE FROM users WHERE user_id = @poster;

-- Rate limit counter -----------------------------------------------
SET @w = '2026-01-01 10:00:00';
INSERT INTO rate_limits (limit_key, window_start) VALUES ('login:email:nobody@x.com', @w)
    ON DUPLICATE KEY UPDATE hit_count = hit_count + 1;
INSERT INTO rate_limits (limit_key, window_start) VALUES ('login:email:nobody@x.com', @w)
    ON DUPLICATE KEY UPDATE hit_count = hit_count + 1;
SELECT 'OK: rate-limit counter should show hit_count = 2 (works with no user row) [T-15]' AS test,
       hit_count FROM rate_limits WHERE limit_key = 'login:email:nobody@x.com';

-- E13 --------------------------------------------------------------
SELECT 'E13 plain duplicate rate-limit row (EXPECT ERROR 1062)' AS test;
INSERT INTO rate_limits (limit_key, window_start) VALUES ('login:email:nobody@x.com', @w);

-- Audit log has no FK on target: this must succeed ------------------
INSERT INTO audit_logs (actor_id, action, target_type, target_id) VALUES (@cust, 'test_action', 'item', 999999);
SELECT 'OK: audit row for a non-existent target was accepted' AS test, COUNT(*) AS rows_found
FROM audit_logs WHERE action = 'test_action';

ROLLBACK;
SELECT 'Rolled back. No test data should remain.' AS done,
       (SELECT COUNT(*) FROM users) AS users_left;