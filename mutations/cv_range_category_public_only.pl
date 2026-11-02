# Name a public course's category whether or not it is public.
s/                'categoryid' => isset\(\$public\[\$categoryid\]\) \? \$categoryid : 0,/                'categoryid' => \$categoryid,/;
