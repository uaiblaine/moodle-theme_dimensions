# Stop reading core's completion: the status says "Pending" for an activity core records as complete.
s/return \$details->is_overall_complete\(\) \? self::STATE_COMPLETE : self::STATE_PENDING;/return self::STATE_PENDING;/;
