import sys
import json
import os
import time
from datetime import datetime
from faster_whisper import WhisperModel

# --- Argument Parsing ---
if len(sys.argv) < 3:
    print("Usage: python transcribe.py <input.mp3> <output.json> [model_name] [initial_prompt]")
    sys.exit(1)

NUM_WORKERS = os.cpu_count() or 8
input_path = sys.argv[1]
output_path = sys.argv[2]
model_name = sys.argv[3] if len(sys.argv) > 3 else "large-v3-turbo"
initial_prompt = sys.argv[4] if len(sys.argv) > 4 else None

# --- Timer start ---
t0 = time.time()

# --- Load Model ---
model = WhisperModel(
    model_name,
    device="cpu",          # set "auto" or "metal" on Apple Silicon if desired
    compute_type="int8",
    cpu_threads=NUM_WORKERS,
)

# --- Transcription ---
segments, info = model.transcribe(
    input_path,
    beam_size=1,                 # greedy = fastest on CPU
    language="en",
    word_timestamps=True,
    initial_prompt=initial_prompt,
    temperature=0.0,
)

# --- Build whisper.cpp-like JSON ---
out = {
    "systeminfo": "FASTER_WHISPER",
    "model": {"type": model_name},
    "params": {"language": "en"},
    "result": {"language": "en"},
    "generated_at": datetime.utcnow().isoformat() + "Z",
    "transcription": []
}

for i, segment in enumerate(segments):
    seg_start = segment.start or 0.0
    seg_end = segment.end or seg_start

    # Collect tokens (one per word, with probability)
    tokens = []
    probs = []
    for w in (segment.words or []):
        if w.probability is not None:
            probs.append(w.probability)
        tokens.append({
            "text": w.word,
            "offsets": {
                "from": int(round((w.start or seg_start) * 1000)),
                "to":   int(round((w.end   or seg_end)   * 1000)),
            },
            "p": float(w.probability) if w.probability is not None else None,
        })

    avg_conf = round(sum(probs) / len(probs), 3) if probs else None

    out["transcription"].append({
        "id": i,
        "timestamps": {
            "from": f"{int(seg_start//60):02d}:{(seg_start%60):05.2f}".replace('.', ','),
            "to":   f"{int(seg_end//60):02d}:{(seg_end%60):05.2f}".replace('.', ','),
        },
        "offsets": {"from": int(round(seg_start * 1000)), "to": int(round(seg_end * 1000))},
        "text": segment.text.strip(),
        "tokens": tokens,
        "avg_confidence": avg_conf,
    })

# --- Timing info ---
t1 = time.time()
processing_time = t1 - t0
audio_runtime = float(getattr(info, "duration", 0.0) or 0.0)
rtf = (processing_time / audio_runtime) if audio_runtime > 0 else None

# Attach processing metrics to JSON
out["processing"] = {
    "audio_path": os.path.abspath(input_path),
    "audio_duration_sec": round(audio_runtime, 3) if audio_runtime else None,
    "processing_time_sec": round(processing_time, 3),
    "rtf": round(rtf, 3) if rtf is not None else None,
    "cpu_threads": NUM_WORKERS,
}

# --- Save JSON ---
with open(output_path, "w", encoding="utf-8") as f:
    json.dump(out, f, ensure_ascii=False, indent=2)

# --- Console debug ---
print(f"✅ Transcription saved to {output_path}")
if audio_runtime:
    print(f"ℹ️  Audio runtime: {audio_runtime:.2f} seconds")
print(f"⏱️  Processing time: {processing_time:.2f} seconds")
if rtf is not None:
    print(f"⚡ Real-time factor (RTF): {rtf:.2f}x")
