# Answer the guest account.
s/        if \(isguestuser\(\)\) \{\n            throw new refusal\(\);/        if (false) {\n            throw new refusal();/;
