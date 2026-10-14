# Leave a settled id in the pending set. The observer keeps firing while a batch is in flight, so
# a card that scrolled out and back is put back into pending after the batch took it out - and the
# next drain asks the server for it a second time. The sweep exercises amd_source_test's
# assertion, not a browser.
s/        pending\.delete\(id\);\n        const card = cardOf\(id\);/        const card = cardOf(id);/;
