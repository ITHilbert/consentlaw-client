# Optimierungen

Format: `Datum · Auslöser · Ursache · Maßnahme`

- 2026-10-20 · User: Am 16.10. entstanden die Tags 1.0.0, 1.1.0, 1.1.1 und 1.2.0 ungefragt, je direkt nach dem Feature-Merge, obwohl weitere Anpassungen folgen sollten · Regel fehlt: Der Ablauf trennte „Feature fertig“ nicht von „Release“; Spec/Plan nannten eine Ziel-Version, das Spec-OK wurde als Release-OK gewertet; Treiber war, dass die Homepage Package-Änderungen nur per Tag bekommt · Template `0db4950`: Rule `agent-lifecycle.md` „Release nur auf Ansage des Users“, `/push` wählt nie selbst eine Version und fragt bei genannter Version nicht zurück. Offen: Testweg Package → Homepage ohne Tag.
