# Leave the category's default out of the client's addresses on a remembered category.
s/if \(state\.remembered\) \{\n        params\.set\(PARAM\.sort/if (false) {\n        params.set(PARAM.sort/;
