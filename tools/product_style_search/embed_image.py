#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
从单张图片提取 MobileNetV2 全局池化特征（L2 归一化），输出一行 JSON 数组。
用法: python embed_image.py <图片绝对路径>
依赖: pip install -r requirements.txt
"""
from __future__ import annotations

import json
import sys
import warnings
from pathlib import Path

warnings.filterwarnings("ignore", category=UserWarning)

import torch
import torch.nn.functional as F
import torchvision.models as models
import torchvision.transforms as T
from PIL import Image, ImageOps, ImageEnhance

_MODEL = None


def _get_model():
    global _MODEL
    if _MODEL is not None:
        return _MODEL
    try:
        w = getattr(models, "MobileNet_V2_Weights", None)
        if w is not None:
            m = models.mobilenet_v2(weights=w.IMAGENET1K_V1)
        else:
            raise AttributeError("use pretrained")
    except Exception:
        m = models.mobilenet_v2(pretrained=True)
    m.eval()
    for p in m.parameters():
        p.requires_grad = False
    _MODEL = m
    return m


def _preprocess_pil(img: Image.Image) -> Image.Image:
    img = img.convert("RGB")
    img = ImageOps.autocontrast(img, cutoff=1)
    try:
        img = ImageEnhance.Contrast(img).enhance(1.08)
    except Exception:
        pass
    return img


def embed_path(path: str) -> list[float]:
    p = Path(path)
    if not p.is_file():
        raise FileNotFoundError(path)
    img = Image.open(p)
    img = _preprocess_pil(img)
    tfm = T.Compose(
        [
            T.Resize(256, interpolation=T.InterpolationMode.BICUBIC),
            T.CenterCrop(224),
            T.ToTensor(),
            T.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225]),
        ]
    )
    x = tfm(img).unsqueeze(0)
    model = _get_model()
    with torch.no_grad():
        feat = model.features(x)
        feat = F.adaptive_avg_pool2d(feat, (1, 1)).flatten(1)
        feat = F.normalize(feat, dim=1)
    vec = feat.cpu().numpy().flatten().tolist()
    return [float(v) for v in vec]


def main() -> None:
    if len(sys.argv) < 2:
        print(json.dumps({"error": "missing path"}, ensure_ascii=False))
        sys.exit(1)
    path = sys.argv[1]
    try:
        vec = embed_path(path)
        print(json.dumps(vec, ensure_ascii=False))
    except Exception as e:
        # 统一 stdout JSON，便于 PHP 解析
        print(json.dumps({"error": str(e)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
