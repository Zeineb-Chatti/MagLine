from flask import Flask, request, jsonify
import fitz  # PyMuPDF
from transformers import AutoTokenizer, AutoModelForTokenClassification, pipeline
import os
import re
import torch
from typing import List, Set, Dict, Tuple
import logging
from datetime import datetime


# Configuration

MODEL_ID = "Nucha/Nucha_SkillNER_BERT"
MAX_SKILL_LENGTH = 50
MIN_SKILL_LENGTH = 2
API_VERSION = "2.0.0"
MIN_CV_LENGTH = 300  # Minimum characters for valid CV
MIN_SKILLS_THRESHOLD = 5  # Minimum skills to consider CV valid

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('skill_extraction.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

app = Flask(__name__)

# Model Initialization

try:
    tokenizer = AutoTokenizer.from_pretrained(MODEL_ID)
    model = AutoModelForTokenClassification.from_pretrained(MODEL_ID)
    device = 0 if torch.cuda.is_available() else -1

    ner_pipeline = pipeline(
        "ner",
        model=model,
        tokenizer=tokenizer,
        aggregation_strategy="simple",
        device=device,
        ignore_labels=[],
        grouped_entities=True
    )
    logger.info("Model loaded successfully")
except Exception as e:
    logger.error(f"Model initialization failed: {str(e)}")
    raise


# Skill Dictionary 

manual_skill_dictionary = [
    # Programming Languages
    "c", "c++", "java", "python", "javascript", "typescript", "go", "rust",
    "kotlin", "swift", "php", "ruby", "scala", "r", "dart", "html", "css",
    "sql", "bash", "shell", "powershell", "matlab", "perl", "lua", "groovy",

    # Frameworks and Libraries
    "react", "angular", "vue", "django", "flask", "spring", "laravel",
    "ruby on rails", "node.js", "tensorflow", "pytorch", "scikit-learn",
    "pandas", "numpy", "bootstrap", "jquery", "asp.net", "fastapi",

    # Databases
    "mysql", "postgresql", "mongodb", "redis", "oracle", "sqlite",
    "elasticsearch", "firebase", "dynamodb",

    # DevOps & Cloud
    "docker", "kubernetes", "aws", "azure", "google cloud", "terraform",
    "ansible", "jenkins", "github actions", "ci/cd",

    # Data Science & AI
    "machine learning", "deep learning", "data analysis", "data science",
    "natural language processing", "nlp", "computer vision", "big data",
    "artificial intelligence", "ai", "spark", "tableau", "power bi",

    # Web Technologies
    "rest api", "graphql", "json", "xml", "oauth", "jwt", "ssl",

    # Mobile Development
    "android", "ios", "react native", "flutter", "xamarin",

    # Testing
    "unit testing", "selenium", "cypress", "jest", "pytest",

    # Version Control
    "git", "github", "gitlab", "bitbucket",

    # Methodologies
    "agile", "scrum", "devops", "microservices", "object-oriented programming",

    # Soft Skills
    "problem solving", "leadership", "time management"
]

manual_skill_set = {skill.lower() for skill in manual_skill_dictionary}


# Blacklist System

base_blacklist = {
    "skills", "languages", "english", "contact", "email", "phone",
    "resume", "work", "job", "company", "organization", "french",
    "baccalaureate", "computing", "android", "##ops", "##ra", "##rum",
    "##script", "##security 101", "ya habibiiiiiii", "computer", "data",
    "technologies", "strategic", "thinking", "solving", "collaboration",
    "problem-solving", "team", "structures", "systems", "fitness", "sc",
    "erp", "linux", "&", "solution", "management", "development",
    "strategy", "system", "technology", "analysis", "design", "project"
}

job_description_blacklist = base_blacklist.union({
    "years", "experience", "degree", "responsibilities", "requirements",
    "qualifications", "must have", "nice to have", "ability to",
    "strong knowledge of", "candidate", "applicant", "looking for"
})

tech_abbreviations = {
    "ai", "ml", "nlp", "api", "ui", "ux", "css", "html", "json",
    "rest", "http", "https", "sql", "aws", "gcp", "ci", "cd", "devops"
}

tech_capitalization = {
    "php": "PHP",
    "sql": "SQL",
    "bi": "BI",
    "erp": "ERP",
    "ai": "AI",
    "oop": "OOP",
    "devops": "DevOps",
    "api": "API",
    "ui": "UI",
    "ux": "UX",
    "ci/cd": "CI/CD",
    "html": "HTML",
    "css": "CSS",
    "c++": "C++",
    "c#": "C#",
    ".net": ".NET",
    "node.js": "Node.js",
    "ruby on rails": "Ruby on Rails",
    "scikit-learn": "Scikit-learn",
    "object-oriented programming": "OOP"
}



# Helper Functions

def extract_text(path: str) -> str:
    """Enhanced PDF text extraction with better cleaning."""
    try:
        text = ""
        with fitz.open(path) as doc:
            for page in doc:
                text += page.get_text()

        # Enhanced cleaning
        text = re.sub(r'\s*\n\s*', ' ', text)  # Normalize newlines
        text = re.sub(r'[^\w\s+#&./\-]', ' ', text)  # Remove special chars
        text = re.sub(r'\s+', ' ', text).strip()  # Normalize whitespace

        # Remove common non-content elements
        text = re.sub(r'\b(page\s*\d+|email:|phone:).*?\b', '', text, flags=re.I)
        return text
    except Exception as e:
        logger.error(f"PDF extraction failed: {str(e)}")
        raise ValueError(f"PDF extraction failed: {str(e)}")


def normalize_skill(skill: str) -> str:
    """Standardize skill representations."""
    skill = skill.lower().strip()

    variations = {
        'js': 'javascript',
        'ai': 'artificial intelligence',
        'ml': 'machine learning',
        'nlp': 'natural language processing',
        'reactjs': 'react',
        'aws cloud': 'aws',
        'gcp': 'google cloud',
        'k8s': 'kubernetes'
    }

    return variations.get(skill, skill)


def clean_skill(skill: str) -> str:
    """Final perfected skill cleaning with proper capitalization."""
    if not skill:
        return ""

    # Normalize first
    skill = normalize_skill(skill)

    # Remove special characters and normalize
    s = re.sub(r'[^a-zA-Z0-9+#&./\-_ ]', '', skill)
    s = re.sub(r'&\w+', '', s)
    s = re.sub(r'\s*([./+#-])\s*', r'\1', s)
    s = re.sub(r'\s+', ' ', s).strip().lower()

    # Remove standalone single letters except known tech terms
    if len(s) == 1 and s not in {'c', 'r'}:
        return ""

    # Skip skills containing blacklisted words
    for word in base_blacklist:
        if re.search(r'\b' + re.escape(word) + r'\b', s):
            return ""

    # Handle special cases
    if s in tech_capitalization:
        return tech_capitalization[s]

    # Capitalize properly (title case except for conjunctions)
    words = s.split()
    conjunctions = {'and', 'or', 'of', 'for', 'the', 'with', 'in', 'at', 'on'}
    formatted_words = [
        word.capitalize() if word not in conjunctions else word
        for word in words
    ]

    return ' '.join(formatted_words)


def is_valid_skill(skill: str, is_job_description: bool = False) -> bool:
    """Enhanced skill validation with better logic."""
    if not skill or len(skill.strip()) < MIN_SKILL_LENGTH:
        return False

    skill = skill.strip().lower()

    # Skip only exact matches to blacklist
    if skill in base_blacklist:
        return False

    # Be more permissive with multi-word skills
    if len(skill.split()) > 1:
        return True

    # Allow single words that are either in dictionary or look technical
    return (skill in manual_skill_set or
            any(char in skill for char in ['#', '+', '.', '/']) or
            skill.endswith('js') or
            skill in tech_abbreviations)


def merge_similar_skills(skills: Set[str]) -> Set[str]:
    """Enhanced skill consolidation with more variations."""
    skill_variations = {
        "js": "JavaScript",
        "nlp": "Natural Language Processing",
        "ai": "Artificial Intelligence",
        "ml": "Machine Learning",
        "py": "Python",
        "reactjs": "React",
        "aws cloud": "AWS",
        "gcp": "Google Cloud",
        "k8s": "Kubernetes",
        "node": "Node.js",
        "postgres": "PostgreSQL"
    }

    final_skills = set()
    for skill in sorted(skills):
        normalized = skill_variations.get(skill.lower(), skill)

        # Handle plurals
        if normalized.endswith('s'):
            singular = normalized[:-1]
            if singular in final_skills:
                final_skills.remove(singular)
                final_skills.add(normalized)
                continue

        if any(s for s in final_skills if s.lower() == normalized.lower() + 's'):
            continue

        final_skills.add(normalized)

    return final_skills


def extract_skills_from_text(text: str, is_job_description: bool = False) -> List[str]:
    """Enhanced skill extraction pipeline with better NER handling."""
    if not text.strip():
        return []

    logger.info(f"Starting skill extraction (JD: {is_job_description})")

    # NER extraction with confidence threshold
    ner_skills = set()
    try:
        ner_results = ner_pipeline(text)
        for ent in ner_results:
            if ent.get("entity_group") in ["HSKILL", "SSKILL"] and ent.get("score",
                                                                           0) > 0.7:  # Higher confidence threshold
                cleaned = clean_skill(ent["word"])
                if cleaned and len(cleaned.split()) <= 3:  # Limit to 3-word skills
                    ner_skills.add(cleaned)
    except Exception as e:
        logger.error(f"NER failed: {str(e)}")

    # Enhanced dictionary matching
    text_lower = re.sub(r'[^\w\s+#./\-]', ' ', text).lower()
    dict_skills = {
        skill for skill in manual_skill_set
        if re.search(r'(^|\W)' + re.escape(skill) + r'($|\W)', text_lower)
    }

    # Combine and validate
    all_skills = ner_skills.union(dict_skills)
    valid_skills = {
        skill for skill in all_skills
        if is_valid_skill(skill, is_job_description)
    }

    return sorted(merge_similar_skills(valid_skills))


def categorize_skill(skill: str) -> str:
    """Basic skill categorization."""
    skill_lower = skill.lower()

    if any(s in skill_lower for s in ['python', 'java', 'c++', 'javascript', 'typescript']):
        return 'Programming Language'
    elif any(s in skill_lower for s in ['react', 'angular', 'django', 'flask']):
        return 'Framework'
    elif any(s in skill_lower for s in ['mysql', 'postgresql', 'mongodb']):
        return 'Database'
    elif any(s in skill_lower for s in ['docker', 'kubernetes', 'aws']):
        return 'DevOps/Cloud'
    elif any(s in skill_lower for s in ['machine learning', 'ai', 'data science']):
        return 'Data Science/AI'
    return 'Other'



# Updated API Endpoints

@app.route('/validate_cv', methods=['POST'])
def validate_cv():
    """Enhanced CV validation endpoint."""
    try:
        # File handling
        if 'file' in request.files:
            file = request.files['file']
            if not file.filename.lower().endswith('.pdf'):
                return jsonify({'error': 'Only PDF files supported'}), 400
            temp_path = f"/tmp/{file.filename}"
            file.save(temp_path)
            text = extract_text(temp_path)
            os.remove(temp_path)
        elif request.is_json and 'cv_path' in request.json:
            path = request.json['cv_path']
            if not os.path.exists(path):
                return jsonify({'error': 'File not found at provided path'}), 400
            text = extract_text(path)
        else:
            return jsonify({'error': 'No file provided'}), 400

        # Enhanced validation checks
        if len(text.strip()) < MIN_CV_LENGTH:
            return jsonify({'error': 'CV too short or invalid'}), 400

        # Check for resume sections
        required_sections = ['experience', 'education', 'skills']
        text_lower = text.lower()
        found_sections = [section for section in required_sections if section in text_lower]

        if len(found_sections) < 2:
            return jsonify({'error': 'CV missing required sections'}), 400

        # Extract skills as additional validation
        skills = extract_skills_from_text(text)
        if len(skills) < MIN_SKILLS_THRESHOLD:
            return jsonify({'error': 'CV contains too few identifiable skills'}), 400

        return jsonify({
            'valid': True,
            'text_length': len(text),
            'sections_found': found_sections,
            'skills_found': len(skills),
            'message': 'CV validated successfully'
        })
    except Exception as e:
        logger.error(f"CV validation error: {str(e)}")
        return jsonify({'error': str(e)}), 500


@app.route('/extract_skills', methods=['POST'])
def extract_skills():
    """Enhanced skill extraction endpoint."""
    try:
        # File handling
        if request.is_json and 'cv_path' in request.json:
            path = request.json['cv_path']
            if not os.path.exists(path):
                return jsonify({'error': 'File not found'}), 400
            text = extract_text(path)
        elif 'file' in request.files:
            file = request.files['file']
            if not file.filename.lower().endswith('.pdf'):
                return jsonify({'error': 'Only PDF files supported'}), 400
            temp_path = f"/tmp/{file.filename}"
            file.save(temp_path)
            text = extract_text(temp_path)
            os.remove(temp_path)
        else:
            return jsonify({'error': 'No file provided'}), 400

        skills = extract_skills_from_text(text)

        # Add skill categories
        categorized_skills = {
            skill: categorize_skill(skill)
            for skill in skills
        }

        # Group by category
        skills_by_category = {}
        for skill, category in categorized_skills.items():
            skills_by_category.setdefault(category, []).append(skill)

        return jsonify({
            'skills': skills,
            'total_skills': len(skills),
            'skills_by_category': skills_by_category,
            'message': f'Extracted {len(skills)} skills'
        })
    except Exception as e:
        logger.error(f"Skill extraction error: {str(e)}")
        return jsonify({'error': str(e)}), 500



# Endpoints

@app.route('/extract_skills_from_text', methods=['POST'])
def extract_skills_from_text_api():
    """Extract skills from plain text."""
    try:
        data = request.get_json()
        if not data or 'text' not in data:
            return jsonify({'error': 'No text provided'}), 400

        text = data['text']
        is_jd = data.get('is_job_description', False)

        if not text.strip():
            return jsonify({'error': 'Empty text'}), 400

        skills = extract_skills_from_text(text, is_jd)

        return jsonify({
            'skills': skills,
            'total_skills': len(skills),
            'is_job_description': is_jd,
            'message': f'Extracted {len(skills)} skills'
        })
    except Exception as e:
        logger.error(f"Text extraction error: {str(e)}")
        return jsonify({'error': str(e)}), 500


@app.route('/match_skills', methods=['POST'])
def match_skills():
    """Match CV skills against job requirements."""
    try:
        data = request.get_json()
        if not data or 'cv_skills' not in data or 'job_skills' not in data:
            return jsonify({'error': 'Missing skills data'}), 400

        cv_skills = {s.lower() for s in data['cv_skills']}
        job_skills = {s.lower() for s in data['job_skills']}

        matches = cv_skills.intersection(job_skills)
        missing = job_skills - cv_skills
        extra = cv_skills - job_skills

        match_pct = (len(matches) / len(job_skills)) * 100 if job_skills else 0

        return jsonify({
            'exact_matches': sorted(matches),
            'missing_skills': sorted(missing),
            'extra_skills': sorted(extra),
            'match_percentage': round(match_pct, 2),
            'total_job_skills': len(job_skills),
            'total_cv_skills': len(cv_skills)
        })
    except Exception as e:
        logger.error(f"Matching error: {str(e)}")
        return jsonify({'error': str(e)}), 500


@app.route('/health', methods=['GET'])
def health_check():
    """Service health check."""
    return jsonify({
        'status': 'healthy',
        'version': API_VERSION,
        'model_loaded': True,
        'timestamp': datetime.now().isoformat()
    })


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5001, debug=True)
