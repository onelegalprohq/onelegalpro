# ARCH-005 — AI Architecture

**Status:** Approved

## Purpose

Define how AI retrieval, generation, and presentation must behave across OneLegalPro, with specific rules for Thailand-first Legal Intelligence. This document implements Articles 2, 3, 4, 6, and 7 of `docs/architecture/01_OneLegalPro_Constitution.md` and extends the general AI governance rules in `AGENTS.md` and `docs/implementation/02_AI_Developer_Playbook.md`.

## Governing principle

AI output is advisory only. It never directly modifies authoritative legal records, is never presented as official law, and always remains subject to human professional review. Nothing in this document authorizes an exception to that principle.

## Authority-aware retrieval-augmented generation (RAG)

Retrieval must be aware of legal-source authority, not just semantic relevance:

- Every retrievable chunk carries its source document's authority status (`is_authoritative`), jurisdiction, language, and version alongside its text — retrieval and ranking operate on this metadata, not on text similarity alone.
- Official Thai-language sources are prioritized in ranking over translations of the same provision. A translation may be retrieved to aid explanation or as a bridging reference, but never as a substitute ranking signal for the official text.
- When both an official Thai source and its linked translation are candidates for the same question, the official source is retrieved and cited; the translation is retrieved only as a supplementary aid.

## Thai-source retrieval priority

- Any answer touching Thai law must resolve to, and cite, the canonical Thai source document — even if the user asked the question in English and the answer is composed in English.
- If only a translation exists in the index (no linked official source has been ingested yet), the system must say so explicitly rather than presenting the translation as if it were the authoritative text.

## Provenance-preserving chunks

Chunking and embedding pipelines must preserve, per chunk, at minimum:

- Source document identity (UUIDv7)
- Jurisdiction and language
- Authority status (`is_authoritative`)
- Version and effective-date context
- Official source reference (for citation reconstruction)

Provenance metadata must survive the full retrieval → generation → presentation pipeline. An answer must never be composed from a chunk whose provenance has been dropped along the way.

## Translation handling

- Translations assist explanation and readability. They are never the sole authoritative citation for a legal conclusion.
- When a translation is used in generation, the system must identify and expose the corresponding official Thai text, per Constitution Article 3.
- Where no official source has been linked to a translation, the system must flag the gap rather than imply equivalence.

## Disclaimer propagation

Whenever a response surfaces translated legal text — directly or as the basis for an AI-generated explanation — the centrally governed disclaimer (Constitution Article 4) must accompany it. Disclaimer propagation is enforced at the point of presentation, driven by chunk-level provenance metadata, so it cannot be bypassed by generation logic upstream.

## Citation requirements

AI-generated answers referencing Thai law must cite the canonical Thai source using the `LegalCitation` model (see `docs/architecture/09_Legal_Intelligence_Architecture.md`): canonical Thai title, provision/section identifier, official source reference, effective date, and version/amendment status. An English reference citation may be included in addition to, never instead of, the Thai citation.

## Hallucination safeguards

- Generation must not produce statutory text, section numbers, case citations, or amendment history that is not grounded in a retrieved, provenance-tagged chunk. No legal citation may be synthesized from the model's general knowledge.
- Responses must distinguish, in structure, between retrieved official content, retrieved translation content, and any AI-generated explanatory synthesis (Constitution Article 7) — these must never be blended into a single undifferentiated statement of "the law."

## Confidence and source-sufficiency handling

- When retrieval returns no authoritative Thai source for a question, the system must state that no official source was found rather than answering from translation-only or model-only knowledge.
- When retrieved sources are outdated, superseded, or of uncertain currency relative to the effective-date metadata, the system must surface that uncertainty rather than presenting the answer as current law.
- Low-confidence or low-coverage retrieval results must be surfaced as such, not silently upgraded into a confident-sounding answer.

## Human review requirements

Legal Intelligence AI features remain advisory. Outputs that could inform legal judgment must be presented in a way that invites lawyer review (source citations, provenance, disclaimers) rather than a final-answer framing. This does not change the existing AI governance approval gate in `AGENTS.md` for AI governance changes generally.

## Prohibition on presenting generated text as official law

AI-generated explanations, summaries, and commentary must be visually and structurally distinct from official legal source text and from reference translations at every point of presentation. No interface may render AI-generated material in a way that could be mistaken for the official Thai text or an official translation.

## Communications Hub AI

The rules above govern AI over Legal Intelligence content specifically. The following extend the same governing principle — AI output is advisory only, never presented as final, always subject to human review — to AI capabilities within `docs/architecture/11_Communications_Hub_Architecture.md`: the Website AI Receptionist, Email Intelligence, and general AI Assistance across every communication channel.

- **Self-identification.** Any AI-driven conversational surface (Website AI Chat, Client Portal Chat, or an AI-assisted reply) must identify itself as AI; it must never be presented as, or be confusable with, a human staff member.
- **No implied attorney-client relationship.** Engaging with an AI receptionist or assistant does not, by itself, create an attorney-client relationship. This must be structurally clear in how the AI presents itself, consistent with `docs/architecture/01_OneLegalPro_Constitution.md`, Article 12.
- **No legal advice.** Communications AI gathers information, classifies, drafts, and routes; it does not answer what a visitor's or client's legal position is or what they should do about it. Where a question approaches legal-advice territory, the AI's response is to recommend lawyer consultation, not attempt an answer.
- **Provenance on every AI output.** Every AI-produced summary, classification, draft reply, suggested tag, suggested lawyer, or suggested business-object link carries provenance (model/version, timestamp, confidence) — the same provenance discipline this document requires of Legal Intelligence retrieval, applied here to communications AI output.
- **Human review and approval before outbound action.** AI-drafted replies, low-confidence suggested links to a Lead/Client/Matter, and any other AI-proposed action require explicit human approval before taking effect. AI never sends a communication to a Lead or Client without configured human authorization; a narrowly scoped, Firm-configured auto-send exception is the only departure from this default, never the default itself.
- **Escalation on uncertainty.** Where AI confidence in continuing a conversation safely is low, or urgency is detected, the conversation is escalated to a human without restarting it — conversation history and any collected intake data or AI annotations remain intact across the handoff.
