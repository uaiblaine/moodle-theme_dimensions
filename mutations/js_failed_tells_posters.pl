# Stop telling the posters that the latest replacing request failed. Nothing then undoes the pending state: every
# grid stays aria-busy and every pill says "..." until some later request succeeds. The sweep exercises
# listing_account_source_test's assertion.
s/\n        document\.dispatchEvent\(new CustomEvent\(LISTING\.failed\)\);//;
