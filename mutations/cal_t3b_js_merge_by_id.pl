# Merge the answers by position, so a trail of two answers is drawn twice.
s/answer\.trails\.forEach\(\(trail\) => trails\.set\(trail\.id, trails\.get\(trail\.id\) \?\? trail\)\);/answer.trails.forEach((trail) => trails.set(trails.size, trail));/;
