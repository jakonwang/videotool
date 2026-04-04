# 款式图向量提取（Python）

后台导入 CSV 时，PHP 会调用本目录下的 `embed_image.py` 对每张图提取 **MobileNetV2** 全局特征（ImageNet 预训练），用于余弦相似度检索。

## 环境

- Python 3.9+（建议 3.10）
- Windows / Linux 均可

```bash
cd tools/product_style_search
pip install -r requirements.txt
```

首次运行会下载 PyTorch 与 MobileNet 权重，需联网。

## 自检

```bash
python embed_image.py C:\path\to\any.jpg
```

应输出一行 JSON 数组（浮点数列表）。

## 服务器配置

在 `config/product_search.php` 或环境变量中设置：

- `PRODUCT_SEARCH_PYTHON`：若 `python` 不在 PATH，写绝对路径，例如 `C:\Python311\python.exe`。
