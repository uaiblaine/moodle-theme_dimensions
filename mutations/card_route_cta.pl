# A suspended or ended card with a route offers nothing to press.
s/classifier::STATE_SUSPENDED, classifier::STATE_EXPIRED => \$hasroute \? 'category_cta_enrolnow' : null,/classifier::STATE_SUSPENDED, classifier::STATE_EXPIRED => null,/;
