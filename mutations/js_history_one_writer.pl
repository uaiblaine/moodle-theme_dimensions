# The panel spells its history call itself again, outside the one writer.
s/        replaceKeeping\(addressWith\(window\.location\.href, st\.open, st\.psort\)\);/        window.history.replaceState(window.history.state, '', addressWith(window.location.href, st.open, st.psort));/;
