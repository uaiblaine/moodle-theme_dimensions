# A list prop stops being a list of numbers.
s/'perpages' => classreportengine::PERPAGES,/'perpages' => array_map('strval', classreportengine::PERPAGES),/;
