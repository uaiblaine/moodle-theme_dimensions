# Let the guest account write a state.
s/        if \(isguestuser\(\)\) \{\n            throw new refusal\(\);/        if (false) {\n            throw new refusal();/;
