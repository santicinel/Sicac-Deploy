import unittest
from pathlib import Path


RECOMMENDER_PATH = (
    Path(__file__).resolve().parents[1] / "routes" / "recommender.py"
)


class RecommenderPersonaTest(unittest.TestCase):
    def test_recommender_uses_gustavo_persona(self) -> None:
        source = RECOMMENDER_PATH.read_text(encoding="utf-8")

        self.assertIn(
            "Sos Gustavo, el vendedor experto en Inteligencia Artificial de CEA Insumos.",
            source,
        )
        self.assertIn(
            'fallback_prompt = """Sos Gustavo, experto vendedor de seguridad electr\u00f3nica de CEA Insumos.',
            source,
        )
        self.assertNotIn("An\u00edbal", source)
        self.assertNotIn("Anibal", source)


if __name__ == "__main__":
    unittest.main()
