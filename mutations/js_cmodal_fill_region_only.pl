# Fill the whole sheet instead of its content region: the title holding the id the dialogue is labelled by is replaced.
s/const region = body\.querySelector\(SELECTORS\.content\);/const region = body.querySelector(SELECTORS.sheet);/;
