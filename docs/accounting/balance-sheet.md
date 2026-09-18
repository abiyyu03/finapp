# Balance Sheet — Equity Treatment

**Written:** 18 September 2026, before STEP 14 implementation, per spec's own requirement ("Before coding this section, explicitly document the chosen treatment... Do not invent a balancing value.")

## The question

`Assets = Liabilities + Equity` must hold. Equity includes both permanently-posted equity accounts (e.g. Owner Equity from the Opening Balance) and the company's earnings — but MVP has no fiscal period closing (biz §38, explicitly out of scope per biz §41: "Fiscal Period Closing"). Without closing entries, there is no mechanism that ever moves a period's net profit out of "current year" and into a separate "Retained Earnings" account. So: what does the Equity section actually show?

## Decision

**There is one dynamic Equity line, "Current Year Earnings," and no "Retained Earnings" account in the MVP.**

- `Current Year Earnings` = Net Profit (Revenue − Expense) computed live by `ProfitLossService`, for **all posted activity since inception** through the Balance Sheet's As Of Date. It is not a posted journal line or a stored balance — it's computed the same way every time the report renders, from the same source of truth as the P&L (spec §44: posted journals are the only source of truth).
- Every other Equity line comes from actual posted EQUITY-type accounts (e.g. Owner Equity), summed the same way every other account balance in this app is summed — by `AccountBalanceCalculator`.
- `Total Equity = sum(posted EQUITY accounts) + Current Year Earnings`.
- There is no `Retained Earnings` account and no closing entry. Because nothing ever "closes" a period in this MVP, "Current Year Earnings" is not scoped to a fiscal year — it covers everything since the company's first posted journal. That's the honest name for what MVP can actually produce; it stops being accurate the moment fiscal-year closing is added, at which point this document must be revisited.

## Why this is guaranteed to balance

This isn't an assumption — it follows from the double-entry invariant `JournalPostingService` already enforces on every single posted journal (`Σdebit = Σcredit`). Summed across every posted journal in a company:

```
Σ(Assets movements) − Σ(Liabilities movements) − Σ(Equity-account movements) − Σ(Revenue movements) + Σ(Expense movements) = 0
```

Rearranged, using each type's normal balance direction:

```
Assets = Liabilities + Equity-accounts + (Revenue − Expense)
       = Liabilities + Equity-accounts + Current Year Earnings
       = Liabilities + Total Equity
```

So if every posted journal in the system balances, the Balance Sheet balances **by construction** — no plug, no invented value. `BalanceSheetService` still checks `Assets == Liabilities + Equity` explicitly and treats a mismatch as a bug to surface loudly (biz §20: a mismatch is a system error, not a number to just display), not as something that can legitimately happen from correct data.

## What happens when fiscal closing is added later

A future step would add a closing journal that zeroes Revenue/Expense into a real `Retained Earnings` EQUITY account as of the fiscal year end. From that point on, "Current Year Earnings" would only cover posted activity *since the last close*, and Retained Earnings would show up as an ordinary posted EQUITY account like any other — no change to `BalanceSheetService`'s shape, just a `dateFrom` that starts at the last close instead of at company inception.
