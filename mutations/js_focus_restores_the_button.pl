# Focus the load more button while it is still disabled. A disabled element is not focusable, so
# the call is a silent no-op and the reader who pressed it is left on the document body with rows
# they were never taken to - the exact failure the focus management exists for. The sweep
# exercises amd_source_test's assertion, not a browser.
s/        restoreMore\(more\);\n        more\.focus\(\);/        more.focus();/;
