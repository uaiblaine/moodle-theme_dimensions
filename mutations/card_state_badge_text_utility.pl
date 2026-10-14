# Strip the pending pill of its state class: a pill with a fill but no class of its own.
s/classifier::STATE_PENDING => \['dim-badge dim-badge--pending', 'category_state_pending', 'fa-hourglass-half'\],/classifier::STATE_PENDING => ['dim-badge', 'category_state_pending', 'fa-hourglass-half'],/;
