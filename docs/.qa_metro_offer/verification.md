# Final verification

Four requested DOCX outputs are directly in metro/docs. Render v2 is the final verified QA set: 01 = 3 pages, 02 = 2 pages, 03 = 3 pages, 04 = 4 pages. All twelve page PNGs were inspected at readable resolution. No clipping, broken rows, orphan signature blocks, or unwanted blank pages were observed. Letterhead image is visible on page 1 of the three supplier documents. HPS has no supplier letterhead.

Source letterhead SHA256 remains D23F2247881011C3BD38224B929427D1F6D7719055E791155E40CAE163454705. Embedded source image bytes are preserved in the three supplier documents. Supplier identity and director, absence of stale UMKM/ASJ names, absence of tracked revisions, and price strings passed the audit_outputs.ps1 checks. Builder arithmetic reconciled item HPS to 70,000,000 and offer groups to 69,450,000.

Rendering used Word SaveAs2 PDF and pypdfium2 page rasterization because the managed dependency loader and bundled LibreOffice were unavailable. Avoid ComputeStatistics during export in this environment; it caused two task-created Word processes to wait. Exporting copies with explicit string paths and a page-information read succeeded. Both stuck task processes were stopped after identity verification. The generated stale owner lock was archived in QA, not deleted. No user documents or reference sources were deleted.

PPK name and NIP intentionally remain blank for the authorized official. Supplier date uses 9 November 2026 and location Sleman from the letterhead. No letter number, signature image, or stamp was invented.
