# Member ID review

Lendly uses administrator review at `/identity/verify`. Google or email signup confirms the email separately. Members may browse and save rental drafts while waiting; ID approval is required to create/publish listings, submit rental requests or finalize a new booking. Existing rental payments, returns and dispute obligations remain available.

## Member and administrator workflow

1. The member confirms their account name matches their Philippine National ID, accepts processing consent and uploads the ID front, optional back containing the QR, and a clear selfie. JPEG, PNG or WebP, 200–6,000 pixels per side, at most 8 MB each.
2. Lendly runs YuNet face detection and SFace comparison locally. The main ID portrait is aligned and compared with the single selfie face. Missing, too-small or multiple selfie faces stop submission with an actionable error. Runtime failures cannot approve or create a partial submission.
3. An active, email-confirmed administrator opens **ID reviews** at `/admin/identity-submissions`. They inspect the photos and raw cosine score, use [the official National ID Check](https://everify.gov.ph/check), and compare the checker's demographics with the submitted ID and current account name.
4. Approval requires three explicit confirmations: official QR passed, details match, and visual face/score review. Rejection requires a reason displayed to the member; they can upload new photos. The decision and reviewer are recorded. Members approved this way display **ID reviewed**.

The administrator must manually transfer/upload the QR image to the official checker. Opening that site does not send a verification result back to Lendly. The review form records the administrator's attestation; it does not call a government API.

A raw cosine score is not a probability, confidence percentage or automatic verdict. Higher means more similar. No uncalibrated threshold decides access. A selfie photo (including a camera capture) does not establish liveness or prevent replay/deepfake attacks. Reviewers must assess the photos and official QR result together; unclear submissions should be rejected with instructions to retry.

## Railway runtime

The repository's `railpack.json` [extends the normal PHP/Laravel build](https://railpack.com/config/file/). It installs Python and its venv support, installs the pinned face dependencies, downloads the checksum-verified model files, and runs real detector/recognizer inference during the build. A failed check fails that build rather than deploying an image without a working face runtime. The build also adds a PHP ini fragment disabling the PHP version header.

The runtime and models are copied into the final image at `/opt/lendly-face`, outside application upload storage. Both build and runtime configuration use:

```text
FACE_MATCH_PYTHON=/opt/lendly-face/runtime/bin/python
FACE_MATCH_MODELS=/opt/lendly-face/models
```

Check for older service-variable overrides pointing at missing paths. Use Railpack for the application service and keep its generated build/start behavior; no changes to email variables or the database are required. Deploy this revision to the actual Railway-connected repository/branch, then run this inside the **new application container**:

```sh
php artisan identity:check-runtime
```

It must report healthy. This checks actual Python execution, model checksums, loading and inference with a generated blank image; it does not upload an ID, approve a member, or establish recognition accuracy. Then test submission with authorized non-sensitive fixture photos and confirm the pending submission can be reviewed. Passing local mocks is not proof of a working hosted runtime. Failed comparisons continue to block submission; this change does not bypass review or invent a score.

## Local setup

On Linux or macOS, create the environment with `python3.13 -m venv storage/app/private/face-runtime` instead of the Windows `py` command below.

Use Python 3.10–3.13 with the pinned dependencies; Python 3.13 is recommended. Python 3.14 is not supported by the current NumPy 2.2.6 pin. Check `python --version` before creating the environment, or select Python 3.13 explicitly with the Windows Python launcher as shown below. From the project directory:

```powershell
py -3.13 -m venv storage/app/private/face-runtime
& storage/app/private/face-runtime/Scripts/python.exe -m pip install -r scripts/face-match/requirements.txt
& storage/app/private/face-runtime/Scripts/python.exe scripts/face-match/setup_models.py storage/app/private/face-models
php artisan migrate
php artisan config:clear
npm run build
```

On Linux use `face-runtime/bin/python` in place of `face-runtime/Scripts/python.exe`. These private runtime/model directories are ignored by Git. The default Laravel config uses this project's private virtual environment. Set `FACE_MATCH_PYTHON` only when using a different absolute Python executable path. Install the same runtime and weights on each application server. Enable PHP process execution for the app user; comparison has a 45-second timeout. Missing setup produces an honest submission error.

Weights come from [OpenCV Zoo YuNet](https://github.com/opencv/opencv_zoo/tree/main/models/face_detection_yunet) (MIT) and [OpenCV Zoo SFace](https://github.com/opencv/opencv_zoo/tree/main/models/face_recognition_sface) (Apache 2.0). The setup script checks the published Git LFS SHA-256 IDs before accepting either model and saves their license texts alongside them. See [OpenCV's comparison documentation](https://docs.opencv.org/4.x/d0/dd4/tutorial_dnn_face.html) for algorithm details. Benchmark thresholds are not calibrated for photographs of Philippine IDs.

Run the Laravel scheduler (`php artisan schedule:work` for local development, normal per-minute `schedule:run` cron in deployment). `identity:prune-documents` runs hourly and can be invoked manually. It cancels submissions older than 30 days and retries deletion of photos from decided/cancelled reviews. At the 30-day boundary photos immediately become inaccessible to the document endpoint; physical deletion runs on the next scheduler pass. Backups need their own expiry policy.

## Privacy and account changes

Files use generated names under `storage/app/private/identity-documents`. They are never put on the public disk or included in model serialization. A protected, non-cached document endpoint rechecks the current viewer and serves only unexpired pending images to the submitting member or an active, email-confirmed administrator. Keep the private storage outside the web server's document root and disable request-body logging for uploads/Livewire requests.

Photos are deleted after a decision, cancellation, account name/email change, suspension or closure. Temporary upload files are removed on successful submission; abandoned Livewire uploads follow Livewire's normal temporary-file cleanup. Failed or interrupted deletions are retried by the pruning command. Minimal decision metadata remains: member/reviewer IDs, dates, consent date, notes, keyed profile fingerprint and raw similarity. Embeddings, decoded QR data and government demographic responses are not stored by this workflow. Do not paste ID numbers or sensitive demographics into review notes.

User identity references are encrypted and hidden from serialization. Changing name/email, suspension or deletion clears approval and cancels pending submissions. Fresh account fingerprints and locked server transactions prevent stale profiles or concurrent decisions from granting approval. Both member interfaces share the same approval.

## Verification

Laravel tests cover private access, consent/file validation, malformed face results, duplicate/stale decisions, review confirmations, lifecycle cleanup, member/admin screens and rental/listing gates. Local model smoke checks can be run with two non-sensitive single-face photos:

```powershell
& storage/app/private/face-runtime/Scripts/python.exe scripts/face-match/self_check.py storage/app/private/face-models first.jpg second.jpg
```

The check compares the same photo and different people, then asserts blank and multiple-face selfies are rejected. This is a runtime smoke check, not an accuracy/fairness study. Use authorized fixtures; never submit a real member ID in automated tests.

## Legacy official API backend

The earlier `IdentityVerification` service, attempts table and its server-side contract tests remain for compatibility. Its SDK/QR flow is not offered by the active member screen. `IDENTITY_*` settings do not enable or disable manual review. Production approvals previously obtained through that service still count; sandbox results never do. Connecting the official API later requires approved credentials and a separate integration review. [PSA's public-checker advisory](https://psa.gov.ph/content/public-advisory-37) describes the public tool and its intended assurance limits.
