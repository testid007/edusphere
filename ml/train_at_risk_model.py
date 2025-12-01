# EDUSPHERE/ml/train_at_risk_model.py

"""
Train logistic regression model to predict 'at-risk' students
and save coefficients into MySQL table risk_logistic_coeffs.

This script is run offline from the command line, not via the browser.
"""

import math
import mysql.connector
import numpy as np
from sklearn.linear_model import LogisticRegression

# 1) DB connection settings – match your includes/db.php
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",          # your password
    "database": "edusphere"  # your DB name
}


def score_to_percent(score_str: str):
    """Convert strings like '45/50' or '78' to percentage float."""
    if not score_str:
        return None
    s = score_str.strip()
    import re
    m = re.match(r'^(\d+(?:\.\d+)?)(?:\s*/\s*(\d+(?:\.\d+)?))?$', s)
    if not m:
        return None
    obt = float(m.group(1))
    if m.group(2):
        maxm = float(m.group(2))
        if maxm <= 0:
            return None
        return (obt / maxm) * 100.0
    return obt


def fetch_features_and_labels():
    """
    Build feature matrix X and label vector y.

    Features:
      x1 = attendance %
      x2 = average grade %
      x3 = discipline incidents count
      x4 = assignment completion % (placeholder 100 for now)

    Label y:
      1 = at-risk (based on simple rule we used earlier)
      0 = not at-risk
    """
    conn = mysql.connector.connect(**DB_CONFIG)
    cur = conn.cursor(dictionary=True)

    # Attendance
    cur.execute("""
        SELECT student_id,
               SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
               SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_days
        FROM attendance
        GROUP BY student_id
    """)
    attendance = {}
    for row in cur:
        total = row["present_days"] + row["absent_days"]
        if total > 0:
            attendance[row["student_id"]] = (row["present_days"] / total) * 100.0

    # Grades
    cur.execute("SELECT student_id, category, score FROM grades")
    grade_sum = {}
    grade_cnt = {}
    discipline_cnt = {}
    for row in cur:
        sid = row["student_id"]
        p = score_to_percent(row["score"])
        if p is not None:
            grade_sum[sid] = grade_sum.get(sid, 0.0) + p
            grade_cnt[sid] = grade_cnt.get(sid, 0) + 1

        if row["category"] == "Discipline":
            discipline_cnt[sid] = discipline_cnt.get(sid, 0) + 1

    # Build dataset
    X = []
    y = []

    for sid, att in attendance.items():
        avg_grade = None
        if sid in grade_sum and grade_cnt[sid] > 0:
            avg_grade = grade_sum[sid] / grade_cnt[sid]

        if avg_grade is None:
            continue  # skip students without grades

        disc = discipline_cnt.get(sid, 0)
        assign_completion = 100.0  # TODO: real feature later

        # same rule as old PHP simple-risk (so label is consistent)
        is_at_risk = (att < 75) or (avg_grade < 50)
        label = 1 if is_at_risk else 0

        X.append([att, avg_grade, disc, assign_completion])
        y.append(label)

    cur.close()
    conn.close()

    X = np.array(X, dtype=float)
    y = np.array(y, dtype=int)
    return X, y


def train_and_save():
    X, y = fetch_features_and_labels()
    print("Training dataset:", X.shape, "labels:", y.mean(), "avg label (risk rate)")

    if X.shape[0] < 5:
        print("Not enough data to train a stable model.")
        return

    model = LogisticRegression()
    model.fit(X, y)

    # Coefficients
    b0 = float(model.intercept_[0])
    b1, b2, b3, b4 = [float(c) for c in model.coef_[0]]

    print("Intercept (b0):", b0)
    print("b1 (attendance):", b1)
    print("b2 (grade avg):", b2)
    print("b3 (discipline):", b3)
    print("b4 (assign completion):", b4)

    # Save into risk_logistic_coeffs table
    conn = mysql.connector.connect(**DB_CONFIG)
    cur = conn.cursor()
    cur.execute("""
        INSERT INTO risk_logistic_coeffs
        (intercept, beta_attendance, beta_grade_avg, beta_discipline, beta_assign_comp)
        VALUES (%s, %s, %s, %s, %s)
    """, (b0, b1, b2, b3, b4))
    conn.commit()
    cur.close()
    conn.close()
    print("Coefficients saved to risk_logistic_coeffs table.")


if __name__ == "__main__":
    train_and_save()
