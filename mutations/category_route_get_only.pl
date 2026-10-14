# Accept a POST on the public page's route: a surface every control of which is a GET form or a link
# then answers a method nothing on it sends.
s/        method: \['GET'\],/        method: ['GET', 'POST'],/;
