# Leave a settled id in the pending set. The observer keeps firing while a batch is in flight, so a card that scrolled
# out and back is put back into pending after the batch took it out, and stays there for good: the drain filters it
# out of every batch (no request is sent twice), but the set never empties and every drain refilters it. Only the
# source shape sees it (listing_account_source_test); Jest observes no request either way.
s/        pending\.delete\(id\);\n        const card = cardOf\(id\);/        const card = cardOf(id);/;
