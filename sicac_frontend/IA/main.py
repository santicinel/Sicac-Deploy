import uvicorn
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from dotenv import load_dotenv
import os
import logging

# Silence non-fatal PDF parsing warnings from pypdf (malformed manuals).
logging.getLogger("pypdf").setLevel(logging.ERROR)
logging.getLogger("pypdf._reader").setLevel(logging.ERROR)

# Load environment variables
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
load_dotenv(os.path.join(BASE_DIR, ".env"))
load_dotenv()

# Import Routers
from routes.qa import router as qa_router
from routes.technician import router as technician_router
from routes.recommender import router as recommender_router
from routes.claims import router as claims_router
from routes.labour import router as labour_router

# Initialize FastAPI
app = FastAPI(title="SICAC AI API", version="1.0.0")

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include Routers
app.include_router(qa_router)
app.include_router(technician_router)
app.include_router(recommender_router, prefix="/recommender")
app.include_router(claims_router, prefix="/claims")
app.include_router(labour_router)

@app.get("/")
def read_root():
    return {"status": "online", "service": "SICAC AI Assistant Unified Server"}

if __name__ == "__main__":
    try:
        ai_port = int(os.getenv("AI_PORT", "8002"))
    except ValueError:
        ai_port = 8002
    uvicorn.run(app, host="0.0.0.0", port=ai_port)
