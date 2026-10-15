# Draw tracks on a hybrid course too, not only on a hybrid event.
s/ && \$this->is_event\(\) && \$this->is_hybrid\(\) && count/ && \$this->is_hybrid() && count/;
