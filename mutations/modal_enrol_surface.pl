# Let "Enrol" follow the card's link: staff who may walk in are sent to the course by a button that says "Enrol".
s/\$state === classifier::STATE_OPEN \? \$this->url\(false\) : \$url/\$url/;
