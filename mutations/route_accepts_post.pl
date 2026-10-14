# Answer GET only, so every enrolment widget posting to the page's address gets a 405.
s/        method: \['GET', 'POST'\],/        method: ['GET'],/;
