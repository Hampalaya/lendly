from pathlib import Path
import json
import re
from collections import Counter
import xml.etree.ElementTree as ET

root = Path(r'C:\Users\djani\lendly')
audit = root / 'audit'
data = json.loads((audit / 'assessments.json').read_text(encoding='utf-8'))
source = (audit / 'requirements-source.txt').read_text(encoding='utf-8-sig').splitlines()
requirements = {}
for i, line in enumerate(source):
    if re.fullmatch(r'(?:FR|NFR)-\d{2}', line.strip()):
        if i+1 < len(source) and source[i+1].startswith(('The ', 'All user', 'Sensitive data', 'Rental agreements', 'Error messages', 'Core actions', 'Distances shown')):
            requirements[line.strip()] = source[i+1]
assert len(requirements) == 73, len(requirements)
assert len(data['fr']) == 53 and len(data['nfr']) == 20 and len(data['flows']) == 10
assert set(requirements) == {r['id'] for group in ('fr','nfr') for r in data[group]}

def evidence_links(value):
    links = []
    for entry in value.split('; '):
        match = re.fullmatch(r'(.+?)(?::(\d+))?', entry)
        rel, number = match.groups()
        path = root / rel
        if path.exists():
            # Use file links without a guessed line locator. Exact methods are described in findings.
            label = rel
            target = path.as_posix()
            links.append(f'[{label}]({target})')
        else:
            links.append(entry)
    return '; '.join(links)

tests = {}
for key, file in [('Existing suite', 'existing-tests.xml'), ('Audit gap probes', 'gap-probes.xml')]:
    tree = ET.parse(audit / file)
    cases = tree.findall('.//testcase')
    tests[key] = {'tests': len(cases), 'failures': len(tree.findall('.//failure')), 'errors': len(tree.findall('.//error')),
                  'assertions': sum(int(c.attrib.get('assertions', 0)) for c in cases)}
assert tests['Existing suite']['tests'] == 144
assert tests['Audit gap probes']['tests'] == 7
assert all(v['failures'] == 0 and v['errors'] == 0 for v in tests.values())

parts = [
'# Lendly requirements implementation audit',
'',
'Reviewed on 7 October 2026 against Revised_RequirementsDocument.docx and the current Lendly workspace.',
'',
'The system does not fully satisfy the revised document. The core marketplace and rental lifecycle are implemented, but role selection, cancellation consistency, both-party notifications and receipts, public review profiles, live status updates, and several process steps remain incomplete. Quality requirements requiring operational, browser, performance, usability or compliance evidence cannot be confirmed from code alone.',
'',
'## Audit scope and status definitions',
'',
'This audit covers all 53 functional requirements, all 20 non-functional requirements, and all 10 detailed Lendly process flows in Part VII.B. The embedded end-to-end flow diagram and navigation map were also inspected. Earlier bakery and barangay proposals are alternative topics, not requirements for the selected Lendly system. Project schedule completion labels were treated as planning data, not proof of implementation. The supplied document was used as the audit baseline; its content was not treated as instructions to change the system.',
'',
'The review inspected application logic, routes, policies, middleware, models, database migrations, screens, map JavaScript, configuration and tests. Application code, PHP settings and the MySQL database were not changed. Audit files and isolated test probes were added under the audit folder. Tests ran with an in-memory SQLite database and SQLite extensions enabled only for those commands. This is a source-and-automated-test audit, not a deployed-system certification or a complete browser acceptance test.',
'',
'- **Implemented**: The required capability is present in the inspected code, with relevant automated evidence where available. This does not guarantee every production or browser scenario.',
'- **Partial**: Some required behavior exists, but an observed defect, missing clause, process deviation or operational dependency prevents full compliance.',
'- **Unverified**: Evidence is insufficient to determine whether the quality requirement is met; this does not mean the requirement fails.',
'- **Not implemented**: No implementation of the requirement was found. No entire requirement falls in this category, although several mandatory subfeatures are absent.',
'',
'## Status totals',
'',
'| Category | Total | Implemented | Partial | Unverified | Not implemented |',
'| --- | ---: | ---: | ---: | ---: | ---: |',
]
for key, label in [('fr','Functional requirements'), ('nfr','Non-functional requirements'), ('flows','Process flows')]:
    c = Counter(row['status'] for row in data[key])
    parts.append(f"| {label} | {len(data[key])} | {c['Implemented']} | {c['Partial']} | {c['Unverified']} | {c['Not implemented']} |")
parts += ['', 'Counts classify complete requirements, not individual clauses. They are not a weighted completion percentage.', '', '## Verification results', '',
'| Check | Result |', '| --- | --- |']
for label, result in tests.items():
    parts.append(f"| {label} | {result['tests']} tests passed; {result['assertions']} assertions; no failures or errors |")
parts += ['',
'The seven gap probes intentionally assert defective current behavior. Their passing result confirms the gaps; it does not mean those requirements passed. The original test suite passes partly because it tests the current design rather than the revised document: the role test expects a merged member interface, and the test titled for receipts to both parties only checks that the owner sees an earnings breakdown.',
'',
'No frontend browser automation, load benchmark, uptime record, deployed scheduler confirmation, actual email inbox-delivery check, usability study or compliance sign-off was available. The initial test attempts were blocked by runtime/filesystem and missing SQLite support; the results above are from the final successful runs after transient runtime adjustments.',
'',
'## Highest priority gaps',
'',
'1. **Cancellation desynchronizes bookings and availability**. The request-list Cancel action allows an approved request to become cancelled while its rental stays payment-pending and payable. Its dates become available, no fee is recorded and no owner notification is sent. Route approved requests through the booking cancellation operation and reject payments on cancelled requests.',
'2. **Cancellation remains possible after recorded handover**. The booking stays Paid until both pickup acknowledgements, so owner handover alone does not stop cancellation. Check the handover timestamp, not only the combined rental status.',
'3. **Participant authorization for reviews is incomplete**. The owner can open the renter rental component and submit a renter-to-owner review. Enforce the renter identity in each renter-only action; do not rely on general participant view authorization.',
'4. **The login model contradicts the baseline**. Add a selected Owner/Renter session role, role-specific navigation and selected-role access restrictions, or formally revise the baseline to approve the merged member interface.',
'5. **Terms and notifications are incomplete**. Require both recorded acceptance timestamps before booking; send booking/status notifications to both parties. Preserve the agreed terms/policy if they can change later.',
'6. **Historical records are fragile**. Deleted listings disappear from normal rental/request relationships, and account deletion cascades into counterpart transaction records. Preserve historical references or snapshots and define retention behavior.',
'7. **Financial and reservation displays are incomplete**. Provide the owner receipt, reconcile cancelled/refunded earnings, and implement the date-specific Reserved/availability behavior required after payment.',
'8. **Complete remaining user and operational features**. Add public received-review profiles, pin photos, real-time refresh, admin transaction management, and email/scheduler configuration; then validate browser, load and deployment behavior.',
'',
'The missing live payment gateway is not a defect against this baseline: the document explicitly excludes that integration. The current supported payment flow is simulated and must not be described as processing real funds. Courier integration is likewise outside scope.',
]

for key, label in [('fr','Functional requirements'), ('nfr','Non-functional requirements'), ('flows','Process flows')]:
    parts += ['', f'## {label} status matrix', '', '| ID | Requirement or flow | Status |', '| --- | --- | --- |']
    for row in data[key]:
        parts.append(f"| [{row['id']}](#{row['id'].lower()}) | {row['title']} | **{row['status']}** |")
    parts += ['', f'## {label} findings']
    for row in data[key]:
        parts += ['', f"### {row['id']}", '', f"**{row['title']} — {row['status']}**", '']
        if key != 'flows':
            parts += ['Requirement in the document:', '', '> ' + requirements[row['id']], '']
        else:
            parts += [f"Related requirements: {row['related']}.", '']
        parts += [row['finding'], '', '**Evidence:** ' + evidence_links(row['evidence']) + '.']

parts += ['', '## Focused gap checks', '', '| Probe | Observed result |', '| --- | --- |',
'| Approved request cancellation | Request cancelled; booking still payment-pending; no fee/owner notice; dates freed; booking subsequently paid successfully. |',
'| Approval without renter acceptance | Owner acceptance created a rental even though renter acceptance was null; owner had zero confirmation notices. |',
'| Owner invoking renter review | Owner submitted a renter-to-owner review through the renter component. |',
'| Valid zero-coordinate location | A latitude of zero produced no distance even with a radius selected. |',
'| Owner-only handover | Owner pickup timestamp was recorded, but rental remained cancellable. |',
'| Listing deletion and history | Rental/request records remained, but their normal listing relationships returned null. |',
'| Availability changed after opening request form | A new request was accepted after the listing was marked unavailable. |',
'',
'Additional risks such as simultaneous approval/payment, partial database writes, cancelled-earnings reconciliation and JavaScript popup safety were inspected in code rather than exhaustively exercised. Browser map interactions, permission denial, current service connectivity and operational scheduling require acceptance testing.',
'',
'## Process details requiring baseline clarification',
'',
'- B.2 says listings publish after validation; the implementation requires admin approval and reapproval after edits. Decide whether moderation is an intended additional step.',
'- B.2 mentions available dates, while FR-05 only states availability generally. The system offers a boolean availability flag and blocks booked dates; it has no owner-defined availability schedule.',
'- B.4 places terms acceptance after approval. The implementation asks the renter before request submission and the owner during approval. Both-party agreement is the critical invariant; decide whether the exact step order is mandatory.',
'- FR-44 says ongoing after handover, while B.6 includes both Handed Over and Received. The implementation waits for both acknowledgements and uses Active Rental wording. Specify whether owner handover alone should activate a rental.',
'- B.7 describes owner inspection/closure; implementation additionally requires renter return confirmation first. Decide whether that extra dependency is acceptable.',
'- NFR-05 and NFR-18 use acceptable response time and normal load without numeric targets; NFR-09 leaves minimal steps undefined; NFR-08 names no concrete standard; NFR-12 provides no growth volume. Set measurable acceptance criteria before final sign-off.',
'',
'The embedded overview agrees with the main assessed workflow, including both-party terms, Reserved after payment, review publication, cancellation and admin supporting flows. The navigation map has the same selected-role and admin-management gaps identified above. Initial UI images are design references; screenshots alone were not treated as working-feature evidence.',
]
output = audit / 'Lendly_Requirements_Audit.md'
output.write_text('\n'.join(parts) + '\n', encoding='utf-8')
print(f'Report created: {output}')
print(json.dumps(tests))
print(f'Coverage verified: {len(requirements)} requirements and {len(data["flows"])} flows')
