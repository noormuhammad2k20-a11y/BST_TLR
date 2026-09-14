# Automatic order workflow

Received → Pending → Stitching → Ready for Verification → Ready → Delivered.

The saved promised date and exact time are authoritative in the shop timezone. Automatic stages occur at 20%, 50%, and 100% of the interval from booking to the promise. A five-minute order becomes Pending at one minute, Stitching at two minutes thirty seconds, and Ready for Verification at five minutes. These are scheduled estimates; staff must still verify physical completion before marking Ready.

OrderAutoProgress runs regardless of shop opening hours. It catches up missed stages in order under a database row lock, records their effective times in history, and never regresses an order, marks Ready/Delivered, or sends customer messages. Extending a promise recalculates remaining thresholds without undoing stages already reached. Ready and Delivered remain explicit staff actions; existing SMS deduplication and retry controls remain in place.

The existing orders:check-deliveries scheduler invokes progression every minute. Orders pages also catch up on navigation and every ten seconds while visible; an open details modal refreshes when its status changes. The editor is preserved while polling. Shop hours continue to gate internal due alerts only.

On this Windows installation the existing windowless task is Atelier-Delivery-Attention-test-fnal_telor. It invokes php-win.exe directly and requires the computer to be awake and the configured interactive account signed in. On Linux, configure Laravel schedule:run every minute. A missed run catches up on the next scheduler tick or Orders page request.

Regression checks: php vendor/bin/phpunit tests/Feature/OrderAutoProgressTest.php covers five-minute boundaries outside shop hours, delayed catch-up, duplicate runs, manual terminal states, and extended deadlines. MySQL workflow integration tests require the isolated atelier_integrity_test database.

Assigned tailors receive one work credit when staff mark the order Ready. Weekly/monthly piece totals use the completion date, and later delivery does not add a second credit. Ready for Verification remains uncredited until physical completion is confirmed. The staff list and open profile refresh every ten seconds. `orders:backfill-tailor-work --apply` repairs missing credits on existing Ready/Delivered/Completed orders without rewriting existing wages or sending messages; omit --apply to preview candidates. An order needs an assigned staff_id to credit a specific tailor.
