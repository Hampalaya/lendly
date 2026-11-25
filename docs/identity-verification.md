# Member ID review

Lendly uses administrator review at `/identity/verify`. Google or email signup confirms the email separately. Members may browse and save rental drafts while waiting; ID approval is required to create/publish listings, submit rental requests or finalize a new booking. Existing rental payments, returns and dispute obligations remain available.

## Member and administrator workflow

1. The member accepts processing consent, selects their ID type, and uploads a photo ID with their name and portrait, an optional back image, and a selfie. Include the side with the QR code for a Philippine National ID. JPEG, PNG or WebP, 200–6,000 pixels per side, at most 8 MB each.
2. Lendly compares the ID portrait with the selfie locally using YuNet and SFace. It also runs Tesseract on the ID front to read the printed name. When text reading fails, the submission continues to administrator review.
3. An active, email-confirmed administrator opens **ID reviews** at `/admin/identity-submissions`. They confirm the ID type, compare the printed name with the account name, and inspect the photos, face similarity score, and name read by Tesseract.
4. For a Philippine National ID, the administrator scans the QR code and compares the result with the ID using [the official National ID Check](https://everify.gov.ph/check). For school, work, and other photo IDs, they use the issuer's official lookup or contact the issuer through its published contact details. If they cannot confirm the ID, they ask the member to submit a different one.
5. The administrator records the document check and confirms the name and face review before approving. Rejection requires a reason displayed to the member. Members approved this way display **ID reviewed**.

A raw cosine score measures face similarity. The administrator should read the name directly from the photo when the OCR suggestion is missing or inaccurate. Lendly records the completed check that the administrator attests to.

## Railway runtime

The repository's `railpack.json` [extends the normal PHP/Laravel build](https://railpack.com/config/file/). It installs Python and its venv support, installs the pinned face dependencies, downloads the checksum-verified model files, and runs real detector/recognizer inference during the build. A failed check fails that build rather than deploying an image without a working face runtime. The build also adds a PHP ini fragment disabling the PHP version header.

The runtime and models are copied into the final image at `/opt/lendly-face`, outside application upload storage. Both build and runtime configuration use:

```text
FACE_MATCH_PYTHON=/opt/lendly-face/runtime/bin/python
FACE_MATCH_MODELS=/opt/lendly-face/models
```

Use Railpack for the application service. It also installs Tesseract and English OCR data at runtime. Apply the database migrations for the encrypted name field, ID type and verification method when deploying this revision. On the **new application container**, run:

```sh
php artisan identity:check-runtime
tesseract --list-langs
```

The face runtime should report healthy, and the Tesseract language list should include `eng`. Then submit authorized test photos and confirm that the admin queue displays the face score and the extracted name.

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

Install Tesseract with English language data to try name extraction locally. Put it on PATH or set `ID_OCR_BINARY` to its executable path. The app can accept a submission for manual review while Tesseract is unavailable.

Weights come from [OpenCV Zoo YuNet](https://github.com/opencv/opencv_zoo/tree/main/models/face_detection_yunet) (MIT) and [OpenCV Zoo SFace](https://github.com/opencv/opencv_zoo/tree/main/models/face_recognition_sface) (Apache 2.0). The setup script checks the published Git LFS SHA-256 IDs before accepting either model and saves their license texts alongside them. See [OpenCV's comparison documentation](https://docs.opencv.org/4.x/d0/dd4/tutorial_dnn_face.html) for algorithm details. Benchmark thresholds are not calibrated for photographs of Philippine IDs.

Run the Laravel scheduler (`php artisan schedule:work` for local development, normal per-minute `schedule:run` cron in deployment). `identity:prune-documents` runs hourly and can be invoked manually. It cancels submissions older than 30 days and retries deletion of photos from decided/cancelled reviews. At the 30-day boundary photos immediately become inaccessible to the document endpoint; physical deletion runs on the next scheduler pass. Backups need their own expiry policy.

## Privacy and account changes

Files use generated names under `storage/app/private/identity-documents`. They are never put on the public disk or included in model serialization. A protected, non-cached document endpoint rechecks the current viewer and serves only unexpired pending images to the submitting member or an active, email-confirmed administrator. Keep the private storage outside the web server's document root and disable request-body logging for uploads/Livewire requests.

Photos and the encrypted OCR name are cleared after a decision, cancellation, account name/email change, suspension, or closure. Lendly processes OCR on its own server and keeps only the name during review. Temporary upload files are removed on successful submission; abandoned Livewire uploads follow Livewire's normal temporary-file cleanup. The pruning command retries failed file deletions. The review record retains member and reviewer IDs, dates, consent date, notes, the keyed profile fingerprint, the raw similarity score, the ID type and the verification method. Earlier reviews keep their existing decisions and have no recorded verification method. Keep ID numbers and other sensitive details out of review notes.

User identity references are encrypted and hidden from serialization. Changing name/email, suspension or deletion clears approval and cancels pending submissions. Fresh account fingerprints and locked server transactions prevent stale profiles or concurrent decisions from granting approval. Both member interfaces share the same approval.

## Verification

Laravel tests cover private access, consent/file validation, malformed face results, duplicate/stale decisions, review confirmations, lifecycle cleanup, member/admin screens and rental/listing gates. Local model smoke checks can be run with two non-sensitive single-face photos:

```powershell
& storage/app/private/face-runtime/Scripts/python.exe scripts/face-match/self_check.py storage/app/private/face-models first.jpg second.jpg
```

The check compares the same photo and different people, then asserts blank and multiple-face selfies are rejected. This is a runtime smoke check, not an accuracy/fairness study. Use authorized fixtures; never submit a real member ID in automated tests.

## Legacy official API backend

The earlier `IdentityVerification` service, attempts table and its server-side contract tests remain for compatibility. Its SDK/QR flow is not offered by the active member screen. `IDENTITY_*` settings do not enable or disable manual review. Production approvals previously obtained through that service still count; sandbox results never do. Connecting the official API later requires approved credentials and a separate integration review. [PSA's public-checker advisory](https://psa.gov.ph/content/public-advisory-37) describes the public tool and its intended assurance limits.
