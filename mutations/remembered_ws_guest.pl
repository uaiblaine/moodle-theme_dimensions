# Answer the guest account like a real one instead of with the one refusal.
s/        if \(isguestuser\(\)\) \{\n            throw new \\moodle_exception\(guard::ERROR_CATEGORY/        if (false) {\n            throw new \\moodle_exception(guard::ERROR_CATEGORY/;
