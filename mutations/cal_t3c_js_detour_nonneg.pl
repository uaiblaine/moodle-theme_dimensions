# Run the detour's line into the next bar backwards when the turn leaves it no room.
s/across\(bmid, turn, Math\.max\(turn, end - LINK\.head\)\)/across(bmid, turn, end - LINK.head)/;
