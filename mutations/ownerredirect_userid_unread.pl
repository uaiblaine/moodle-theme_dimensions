# Decide the competency page's plan by the request's userid parameter instead of the plan row's.
s/\$planparam = \$script === self::COMPETENCY_SCRIPT \? 'planid' : 'id';/\$planparam = \$script === self::COMPETENCY_SCRIPT ? 'userid' : 'id';/;
