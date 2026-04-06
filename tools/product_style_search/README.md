# 款式图向量提取（Python）

后台导入 **CSV 或 Excel（.xlsx）** 时：Excel 内嵌图由 PHP（PhpSpreadsheet）导出为临时文件；随后调用本目录下的 `embed_image.py` 对每张图提取 **MobileNetV2** 全局特征（ImageNet 预训练），用于余弦相似度检索。

## 环境

- Python 3.9+（建议 3.10）
- Windows / Linux 均可

```bash
cd tools/product_style_search
# 与将要被 PHP 调用的解释器一致（Windows 常用 py -3）
py -3 -m pip install -r requirements.txt
# 或 Linux：python3 -m pip install -r requirements.txt
```

首次运行会下载 PyTorch 与 MobileNet 权重，需联网。

## 自检

```bash
py -3 embed_image.py C:\path\to\any.jpg
# 或 Linux：python3 embed_image.py /path/to/any.jpg
```

应输出一行 JSON 数组（浮点数列表）。

## 服务器配置

**phpStudy / Apache 等 Web 进程的 PATH 通常与用户 CMD 不同**，终端里能用 `python` 不代表 PHP `exec` 能找到。建议在 `config/product_search.php` 的 `python_bin` 或环境变量中设置：

- `PRODUCT_SEARCH_PYTHON` / `python_bin`：`python.exe` 或 `python3` 的**绝对路径**，例如 `C:\Users\xxx\AppData\Local\Programs\Python\Python311\python.exe`。
- 留空时：代码在 Windows 上默认使用 `py -3`，在 Linux 上使用 `python3`（仍依赖系统 PATH 中存在对应命令）。
