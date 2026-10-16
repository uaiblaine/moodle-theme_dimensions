# Leave the term in the state when its chip is pressed: the chip goes and the listing stays searched.
s/if \(param === PARAM\.query\) \{\n        state\.query = '';/if (param === PARAM.query) {\n        state.query = state.query;/;
