# Stop telling the posters that the latest replacing request failed. Nothing then undoes the pending
# state: every grid stays aria-busy and every pill says "..." - the text each poster's control is
# described by - until some later request succeeds. The sweep exercises amd_source_test's assertion.
s/\n            document\.dispatchEvent\(new CustomEvent\(EVENTS\.failed\)\);//;
