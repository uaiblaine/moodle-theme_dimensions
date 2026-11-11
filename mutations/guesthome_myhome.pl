# Hand the guest HOMEPAGE_SITE whatever enablemyhome says, so with the dashboard off core turns it into /my/ and the guest loops.
s/empty\(\$CFG->enablemyhome\) \? null : HOMEPAGE_SITE/HOMEPAGE_SITE/;
