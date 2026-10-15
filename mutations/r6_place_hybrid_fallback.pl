# Leave a hybrid course without its split with no place block at all.
s/\(!\$this->is_inperson\(\) && !\$hybridcourse\)/!\$this->is_inperson()/;
