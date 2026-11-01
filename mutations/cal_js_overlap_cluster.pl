# Keep a cluster open at a touching end.
s/        if \(start >= clusterend\) \{/        if (start > clusterend) {/;
