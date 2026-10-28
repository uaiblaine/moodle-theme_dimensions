# Write a poster's shell from the listing again: two writers of the posters on the logged-in shell, the
# component's draw and this one racing.
s/(const paintShells = \(response\) => \{\n    const accordion = response\.accordion \|\| \[\];\n)/$1    accordion.forEach((group) => {\n        const poster = root.querySelector(SELECTORS.area + '[data-categoryid="' + Number(group.id) + '"]');\n        if (poster) {\n            poster.dataset.ctaurl = group.ctaurl || '';\n        }\n    });\n/;
