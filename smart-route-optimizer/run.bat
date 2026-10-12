@echo off
cd /d "%~dp0"
echo Starting Smart-Route AI Optimizer...
python -m uvicorn app:app --reload --host 0.0.0.0 --port 8001
pause