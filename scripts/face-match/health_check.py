"""Check real runtime/model loading and inference without using anyone's ID or selfie."""

import argparse
import hashlib
from pathlib import Path

from setup_models import MODELS


def check(models):
    import cv2
    import numpy as np

    for model, expected in MODELS.items():
        path = models / Path(model).name
        if hashlib.sha256(path.read_bytes()).hexdigest() != expected:
            raise RuntimeError("Model checksum mismatch: " + path.name)
    cv2.setNumThreads(2)
    detector = cv2.FaceDetectorYN.create(
        str(models / "face_detection_yunet_2023mar.onnx"), "", (320, 320), 0.9, 0.3, 5000
    )
    recognizer = cv2.FaceRecognizerSF.create(
        str(models / "face_recognition_sface_2021dec.onnx"), ""
    )
    blank = np.zeros((320, 320, 3), dtype=np.uint8)
    _, faces = detector.detect(blank)
    if faces is not None and len(faces):
        raise RuntimeError("Blank image unexpectedly detected a face")
    feature = recognizer.feature(cv2.resize(blank, (112, 112)))
    if not np.isfinite(feature).all():
        raise RuntimeError("Recognizer produced invalid features")
    print("Face runtime healthy: model checksums, detector and recognizer inference passed.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("models", type=Path)
    check(parser.parse_args().models)
