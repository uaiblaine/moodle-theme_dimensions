# Every method reads any context as a category's: the context-level guards always pass.
s/\$context->contextlevel != CONTEXT_COURSECAT/false/g;
