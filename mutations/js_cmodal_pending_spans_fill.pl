# Stop waiting for the content: the press's pending ends on the skeleton, and Behat reads a dialogue still loading.
s/    await fill\(body, head, request, link\);/    fill(body, head, request, link);/;
