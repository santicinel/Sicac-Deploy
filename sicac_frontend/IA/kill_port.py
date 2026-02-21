import subprocess
import re
import os

def kill_port(port):
    try:
        # Run netstat to find the PID
        result = subprocess.check_output(f"netstat -ano | findstr :{port}", shell=True).decode()
        
        # Parse the PID
        for line in result.splitlines():
            if "LISTENING" in line:
                # Line format: TCP    0.0.0.0:8000           0.0.0.0:0              LISTENING       PID
                parts = line.split()
                pid = parts[-1]
                
                if pid != "0":
                    print(f"[Auto-Fix] Killing process {pid} on port {port}...")
                    subprocess.run(f"taskkill /F /PID {pid}", shell=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except subprocess.CalledProcessError:
        # No process found, which is good
        pass
    except Exception as e:
        print(f"Error checking port {port}: {e}")

if __name__ == "__main__":
    try:
        ai_port = int(os.getenv("AI_PORT", "8002"))
    except ValueError:
        ai_port = 8002
    kill_port(ai_port)
